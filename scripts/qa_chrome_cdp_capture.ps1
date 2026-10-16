param(
    [string]$Url = 'http://localhost:8000/?theme_preview=premium-photo',
    [string]$Output = 'premium-photo-cdp.png',
    [int]$Width = 1280,
    [int]$Height = 800,
    [int]$Port = 9333,
    [int]$WaitMilliseconds = 4000,
    [switch]$CaptureViewportOnly,
    [string]$TimelineStartOutput = '',
    [string]$TimelineActiveStartOutput = '',
    [string]$InteractionOutput = '',
    [switch]$OpenNavigation,
    [string]$NavigationOutput = '',
    [string]$ScrollSelector = '',
    [string]$ScrolledOutput = '',
    [string]$ClickSelector = '',
    [string]$ClickedOutput = '',
    [string]$ChromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
)

$ErrorActionPreference = 'Stop'

function Send-CdpCommand {
    param(
        [System.Net.WebSockets.ClientWebSocket]$Socket,
        [int]$Id,
        [string]$Method,
        [hashtable]$Params = @{}
    )

    $payload = @{ id = $Id; method = $Method; params = $Params } | ConvertTo-Json -Depth 12 -Compress
    $bytes = [Text.Encoding]::UTF8.GetBytes($payload)
    $Socket.SendAsync(
        [ArraySegment[byte]]::new($bytes),
        [Net.WebSockets.WebSocketMessageType]::Text,
        $true,
        [Threading.CancellationToken]::None
    ).GetAwaiter().GetResult()

    while ($true) {
        $stream = [IO.MemoryStream]::new()
        do {
            $buffer = [byte[]]::new(65536)
            $result = $Socket.ReceiveAsync(
                [ArraySegment[byte]]::new($buffer),
                [Threading.CancellationToken]::None
            ).GetAwaiter().GetResult()
            $stream.Write($buffer, 0, $result.Count)
        } while (-not $result.EndOfMessage)

        $message = [Text.Encoding]::UTF8.GetString($stream.ToArray()) | ConvertFrom-Json
        if ($message.id -eq $Id) {
            if ($message.error) {
                throw "CDP $Method selhalo: $($message.error | ConvertTo-Json -Compress)"
            }
            return $message.result
        }
    }
}

$debugEndpoint = "http://127.0.0.1:$Port/json/list"
$chromeProcess = $null
try {
    $null = Invoke-RestMethod -Uri $debugEndpoint -TimeoutSec 1
} catch {
    if (-not (Test-Path -LiteralPath $ChromePath)) {
        throw "Chrome nebyl nalezen: $ChromePath"
    }
    $profilePath = Join-Path ([IO.Path]::GetTempPath()) "seo-editor-premium-photo-$([Guid]::NewGuid().ToString('N'))"
    New-Item -ItemType Directory -Path $profilePath | Out-Null
    $chromeProcess = Start-Process -FilePath $ChromePath -WindowStyle Hidden -PassThru -ArgumentList @(
        '--headless=new',
        '--no-sandbox',
        '--force-device-scale-factor=1',
        "--remote-debugging-port=$Port",
        "--user-data-dir=$profilePath",
        "--window-size=$Width,$Height",
        '--disable-gpu',
        '--use-angle=swiftshader',
        '--use-gl=angle',
        '--disable-gpu-sandbox',
        '--hide-scrollbars',
        $Url
    )
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $null = Invoke-RestMethod -Uri $debugEndpoint -TimeoutSec 1
            $ready = $true
            break
        } catch {
            Start-Sleep -Milliseconds 250
        }
    }
    if (-not $ready) {
        throw "Chrome DevTools se na portu $Port nespustil."
    }
}

$targets = Invoke-RestMethod -Uri $debugEndpoint
$target = $targets.Where({ $_.type -eq 'page' }) | Select-Object -First 1
if (-not $target) {
    throw "Na portu $Port neni dostupna zadna Chrome stranka."
}

$socket = [Net.WebSockets.ClientWebSocket]::new()
$socket.ConnectAsync(
    [Uri]$target.webSocketDebuggerUrl,
    [Threading.CancellationToken]::None
).GetAwaiter().GetResult() | Out-Null
$id = 0

Send-CdpCommand $socket (++$id) 'Page.enable' | Out-Null
Send-CdpCommand $socket (++$id) 'Runtime.enable' | Out-Null
Send-CdpCommand $socket (++$id) 'Network.enable' | Out-Null
Send-CdpCommand $socket (++$id) 'Network.setCacheDisabled' @{ cacheDisabled = $true } | Out-Null
Send-CdpCommand $socket (++$id) 'Emulation.setDeviceMetricsOverride' @{
    width = $Width
    height = $Height
    deviceScaleFactor = 1
    mobile = $false
} | Out-Null
Send-CdpCommand $socket (++$id) 'Page.navigate' @{ url = $Url } | Out-Null
Start-Sleep -Milliseconds $WaitMilliseconds

if ($CaptureViewportOnly) {
    $viewportCapture = Send-CdpCommand $socket (++$id) 'Page.captureScreenshot' @{
        format = 'png'
        fromSurface = $true
        captureBeyondViewport = $false
    }
    $outputPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $Output))
    [IO.File]::WriteAllBytes($outputPath, [Convert]::FromBase64String($viewportCapture.data))
    $viewportInspection = Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
        expression = @'
(() => {
  const layer = document.querySelector('.gb-welcome-layer');
  const rect = layer?.getBoundingClientRect();
  const hero = document.querySelector('.pf-hero,.bl-hero,.fq-hero,.kt-hero,.page-hero,.pf-detail-hero');
  const heroRect = hero?.getBoundingClientRect();
  const heroContainer = hero?.querySelector(':scope > .container');
  const heroContainerRect = heroContainer?.getBoundingClientRect();
  const heroTitle = hero?.querySelector('h1');
  const heroTitleRect = heroTitle?.getBoundingClientRect();
  const heroDescription = hero?.querySelector('p');
  const heroDescriptionRect = heroDescription?.getBoundingClientRect();
  return {
    innerWidth: window.innerWidth,
    innerHeight: window.innerHeight,
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
    horizontalOverflow: document.documentElement.scrollWidth !== document.documentElement.clientWidth,
    innerHero: hero ? {
      className: hero.className,
      x: heroRect.x,
      y: heroRect.y,
      width: heroRect.width,
      height: heroRect.height,
      paddingTop: getComputedStyle(hero).paddingTop,
      paddingBottom: getComputedStyle(hero).paddingBottom,
      alignItems: getComputedStyle(hero).alignItems,
      container: heroContainer ? { x: heroContainerRect.x, y: heroContainerRect.y, width: heroContainerRect.width, height: heroContainerRect.height } : null,
      title: heroTitle ? { x: heroTitleRect.x, y: heroTitleRect.y, width: heroTitleRect.width, height: heroTitleRect.height, fontSize: getComputedStyle(heroTitle).fontSize, lineHeight: getComputedStyle(heroTitle).lineHeight } : null,
      description: heroDescription ? { x: heroDescriptionRect.x, y: heroDescriptionRect.y, width: heroDescriptionRect.width, height: heroDescriptionRect.height, fontSize: getComputedStyle(heroDescription).fontSize, lineHeight: getComputedStyle(heroDescription).lineHeight } : null
    } : null,
    stackBottom: document.elementsFromPoint(window.innerWidth / 2, window.innerHeight * .78).slice(0, 6).map(element => ({
      tag: element.tagName,
      className: element.className,
      background: getComputedStyle(element).background,
      zIndex: getComputedStyle(element).zIndex
    })),
    layer: layer ? {
      x: rect.x,
      y: rect.y,
      width: rect.width,
      height: rect.height,
      opacity: getComputedStyle(layer).opacity,
      backgroundImage: getComputedStyle(layer).backgroundImage,
      progress: layer.querySelector('[data-welcome-progress]')?.textContent || ''
    } : null
  };
})()
'@
        returnByValue = $true
    }
    $socket.Dispose()
    if ($chromeProcess -and -not $chromeProcess.HasExited) {
        $chromeProcess.Kill()
    }
    [pscustomobject]@{
        Screenshot = $outputPath
        Width = $Width
        Height = $Height
        Runtime = $viewportInspection.result.value
    } | ConvertTo-Json -Depth 8
    exit 0
}

$navigationState = $null
if ($OpenNavigation) {
    $navigationResult = Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
        expression = @'
(async () => {
  const toggle = document.querySelector('.gb-menu,.nav-toggle');
  if (!toggle) return { found:false, open:false };
  const isOpen = () => document.body.classList.contains('gb-nav-open') || document.body.classList.contains('nav-open');
  if (!isOpen()) toggle.click();
  await new Promise(resolve => setTimeout(resolve, 350));
  return {
    found:true,
    open:isOpen(),
    toggleClass:toggle.className,
    ariaExpanded:toggle.getAttribute('aria-expanded')
  };
})()
'@
        returnByValue = $true
        awaitPromise = $true
    }
    $navigationState = $navigationResult.result.value
    if (-not $navigationState.found -or -not $navigationState.open) {
        throw 'Pozadovana navigace se neotevrela; screenshot nebyl vytvoren jako uspesny fallback.'
    }
}

$scrolledOutputPath = $null
$scrolledState = $null
if ($ScrollSelector -ne '') {
    $selectorJson = $ScrollSelector | ConvertTo-Json -Compress
    $scrollResult = Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
        expression = @"
(async () => {
  const section = document.querySelector($selectorJson);
  if (!section) return { found:false };
  document.documentElement.style.scrollBehavior = 'auto';
  const top = section.getBoundingClientRect().top + window.scrollY;
  window.scrollTo(0, Math.max(0, top - window.innerHeight * .25));
  await new Promise(resolve => setTimeout(resolve, 220));
  return {
    found:true,
    scrollY:window.scrollY,
    active:Array.from(document.querySelectorAll('.site-nav a[aria-current]')).map(link => ({
      text:link.textContent.trim(),
      href:link.getAttribute('href'),
      current:link.getAttribute('aria-current'),
      underline:getComputedStyle(link, '::before').content
    }))
  };
})()
"@
        returnByValue = $true
        awaitPromise = $true
    }
    $scrolledState = $scrollResult.result.value
    if ($ScrolledOutput -ne '') {
        $scrolledCapture = Send-CdpCommand $socket (++$id) 'Page.captureScreenshot' @{
            format = 'png'
            fromSurface = $true
        }
        $scrolledOutputPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $ScrolledOutput))
        [IO.File]::WriteAllBytes($scrolledOutputPath, [Convert]::FromBase64String($scrolledCapture.data))
    }
}

$clickedOutputPath = $null
$clickedState = $null
if ($ClickSelector -ne '') {
    $clickSelectorJson = $ClickSelector | ConvertTo-Json -Compress
    $clickResult = Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
        expression = @"
(async () => {
  const control = document.querySelector($clickSelectorJson);
  if (!control) return { found:false };
  const before = document.querySelector('.premium-review-track')?.style.transform || '';
  control.click();
  await new Promise(resolve => setTimeout(resolve, 520));
  const track = document.querySelector('.premium-review-track');
  return {
    found:true,
    before,
    after:track?.style.transform || '',
    reviewCount:track?.querySelectorAll(':scope > .review').length || 0,
    controlsDisplay:getComputedStyle(control.parentElement).display,
    previousDisabled:Boolean(document.querySelector('.premium-review-arrow--previous')?.disabled),
    nextDisabled:Boolean(document.querySelector('.premium-review-arrow--next')?.disabled),
    pricingHidden:Boolean(document.querySelector('#cenik')?.hidden),
    faqHidden:Boolean(document.querySelector('#faq')?.hidden),
    portfolioVisibleCards:document.querySelectorAll('#nase-prace .pt-tile:not(.gb-portfolio-is-hidden)').length,
    portfolioHiddenCards:document.querySelectorAll('#nase-prace .pt-tile.gb-portfolio-is-hidden').length,
    portfolioControlHidden:Boolean(document.querySelector('.gb-portfolio-more')?.hidden),
    portfolioGridHeight:document.querySelector('#homepage-portfolio-grid')?.getBoundingClientRect().height || 0,
    faqOpenCount:document.querySelectorAll('.hp-faq-item.is-open').length,
    navigationOpen:document.body.classList.contains('gb-nav-open') || document.body.classList.contains('nav-open'),
    cookieConsentVisible:Boolean(document.querySelector('[data-cookie-consent]:not([hidden])'))
  };
})()
"@
        returnByValue = $true
        awaitPromise = $true
    }
    $clickedState = $clickResult.result.value
    if ($ClickedOutput -ne '') {
        $clickedCapture = Send-CdpCommand $socket (++$id) 'Page.captureScreenshot' @{
            format = 'png'
            fromSurface = $true
        }
        $clickedOutputPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $ClickedOutput))
        [IO.File]::WriteAllBytes($clickedOutputPath, [Convert]::FromBase64String($clickedCapture.data))
    }
}

$navigationOutputPath = $null
if ($NavigationOutput -ne '') {
    $navigationCapture = Send-CdpCommand $socket (++$id) 'Page.captureScreenshot' @{
        format = 'png'
        fromSurface = $true
    }
    $navigationOutputPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $NavigationOutput))
    [IO.File]::WriteAllBytes($navigationOutputPath, [Convert]::FromBase64String($navigationCapture.data))
}

$timelineStartOutputPath = $null
$timelineActiveStartOutputPath = $null
if ($TimelineStartOutput -ne '') {
    Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
        expression = @'
(async () => {
  const timeline = document.querySelector('.timeline');
  const list = timeline?.querySelector('.tl-list');
  if (!timeline || !list) return false;
  document.documentElement.style.scrollBehavior = 'auto';
  const listTop = list.getBoundingClientRect().top + window.scrollY;
  window.scrollTo(0, Math.max(0, listTop - window.innerHeight * .45 - 24));
  await new Promise(resolve => setTimeout(resolve, 180));
  return true;
})()
'@
        returnByValue = $true
        awaitPromise = $true
    } | Out-Null
    $timelineStartCapture = Send-CdpCommand $socket (++$id) 'Page.captureScreenshot' @{
        format = 'png'
        fromSurface = $true
    }
    $timelineStartOutputPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $TimelineStartOutput))
    [IO.File]::WriteAllBytes($timelineStartOutputPath, [Convert]::FromBase64String($timelineStartCapture.data))
    if ($TimelineActiveStartOutput -ne '') {
        Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
            expression = '(async () => { window.scrollBy(0, 120); await new Promise(resolve => setTimeout(resolve, 180)); })()'
            awaitPromise = $true
        } | Out-Null
        $timelineActiveStartCapture = Send-CdpCommand $socket (++$id) 'Page.captureScreenshot' @{
            format = 'png'
            fromSurface = $true
        }
        $timelineActiveStartOutputPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $TimelineActiveStartOutput))
        [IO.File]::WriteAllBytes($timelineActiveStartOutputPath, [Convert]::FromBase64String($timelineActiveStartCapture.data))
    }
    Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
        expression = '(async () => { window.scrollTo(0, 0); await new Promise(resolve => setTimeout(resolve, 100)); })()'
        awaitPromise = $true
    } | Out-Null
}

$interaction = Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
    expression = @'
(async () => {
  const timeline = document.querySelector('.timeline');
  const playhead = document.querySelector('.tl-playhead');
  const rail = document.querySelector('.tl-rail');
  const timelineList = timeline?.querySelector('.tl-list');
  const timelineHeading = timeline?.querySelector('.section-title');
  const parallax = document.querySelector('[data-parallax]');
  const visualState = (element) => {
    if (!element) return null;
    const rect = element.getBoundingClientRect();
    const style = getComputedStyle(element);
    return {
      x: rect.x,
      y: rect.y,
      width: rect.width,
      height: rect.height,
      display: style.display,
      position: style.position,
      zIndex: style.zIndex,
      opacity: style.opacity,
      visibility: style.visibility,
      backgroundColor: style.backgroundColor
    };
  };
  const before = {
    scrollY: window.scrollY,
    timelineTop: timeline ? timeline.getBoundingClientRect().top + window.scrollY : null,
    timelineHeight: timeline?.getBoundingClientRect().height || null,
    playheadTop: playhead?.style.top || '',
    parallaxTransform: parallax?.style.transform || '',
    heading: visualState(timelineHeading),
    list: visualState(timelineList),
    rail: visualState(rail),
    playhead: visualState(playhead)
  };
  document.documentElement.style.scrollBehavior = 'auto';
  window.scrollTo(0, 400);
  await new Promise(resolve => setTimeout(resolve, 140));
  const parallaxAt400 = parallax?.style.transform || '';
  if (timeline) {
    window.scrollTo(0, before.timelineTop + timeline.offsetHeight * .35);
    await new Promise(resolve => setTimeout(resolve, 180));
  }
  const after = {
    scrollY: window.scrollY,
    playheadTop: playhead?.style.top || '',
    parallaxTransform: parallax?.style.transform || '',
    activeItems: document.querySelectorAll('.tl-item.on').length,
    timelineTrackStart: timeline ? getComputedStyle(timeline).getPropertyValue('--timeline-track-start').trim() : '',
    timelineProgressHeight: timeline ? getComputedStyle(timeline).getPropertyValue('--timeline-progress-height').trim() : '',
    playheadVisible: playhead?.classList.contains('is-visible') || false,
    heading: visualState(timelineHeading),
    list: visualState(timelineList),
    rail: visualState(rail),
    playhead: visualState(playhead)
  };
  return { before, parallaxAt400, after };
})()
'@
    returnByValue = $true
    awaitPromise = $true
}

$interactionOutputPath = $null
if ($InteractionOutput -ne '') {
    $interactionCapture = Send-CdpCommand $socket (++$id) 'Page.captureScreenshot' @{
        format = 'png'
        fromSurface = $true
    }
    $interactionOutputPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $InteractionOutput))
    [IO.File]::WriteAllBytes($interactionOutputPath, [Convert]::FromBase64String($interactionCapture.data))
}

$lazyLoadResult = Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
    expression = @'
(async () => {
  const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
  const viewportStep = Math.max(240, Math.floor(window.innerHeight * .75));
  const initialHeight = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight);
  for (let y = 0; y < initialHeight; y += viewportStep) {
    window.scrollTo(0, y);
    await wait(80);
  }
  window.scrollTo(0, initialHeight);
  await wait(180);
  if (document.fonts?.ready) await document.fonts.ready;
  await Promise.all(Array.from(document.images).map(async image => {
    if (!image.complete) {
      await Promise.race([
        new Promise(resolve => image.addEventListener('load', resolve, { once: true })),
        new Promise(resolve => image.addEventListener('error', resolve, { once: true })),
        wait(2500)
      ]);
    }
    if (image.complete && image.naturalWidth > 0 && image.decode) {
      try { await image.decode(); } catch (_) {}
    }
  }));
  window.scrollTo(0, 0);
  await wait(180);
  const images = Array.from(document.images);
  const requiredImages = images.filter(image =>
    (image.hasAttribute('src') && image.getAttribute('src').trim() !== '') ||
    (image.hasAttribute('srcset') && image.getAttribute('srcset').trim() !== '')
  );
  const visibleImages = requiredImages.filter(image => image.getClientRects().length > 0);
  return {
    imageCount: images.length,
    requiredImageCount: requiredImages.length,
    visibleImageCount: visibleImages.length,
    loadedVisibleImages: visibleImages.filter(image => image.complete && image.naturalWidth > 0).length,
    failedVisibleImages: visibleImages.filter(image => !image.complete || image.naturalWidth === 0).map(image => image.currentSrc || image.src),
    fontsReady: document.fonts ? document.fonts.status === 'loaded' : null
  };
})()
'@
    returnByValue = $true
    awaitPromise = $true
}

$pageHeight = 0
$heightStable = $false
for ($attempt = 0; $attempt -lt 5; $attempt++) {
    $heightResult = Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
        expression = 'Math.ceil(Math.max(document.documentElement.scrollHeight, document.body.scrollHeight))'
        returnByValue = $true
    }
    $measuredHeight = [Math]::Max($Height, [int]$heightResult.result.value)
    if ($measuredHeight -eq $pageHeight) {
        $heightStable = $true
        break
    }
    $pageHeight = $measuredHeight
    Send-CdpCommand $socket (++$id) 'Emulation.setDeviceMetricsOverride' @{
        width = $Width
        height = $pageHeight
        deviceScaleFactor = 1
        mobile = $false
    } | Out-Null
    Start-Sleep -Milliseconds 180
}
if (-not $heightStable) {
    throw "Vyska stranky se po peti merenich neustalila (posledni hodnota: $pageHeight px)."
}

$capture = Send-CdpCommand $socket (++$id) 'Page.captureScreenshot' @{
    format = 'png'
    fromSurface = $true
    captureBeyondViewport = $false
}

$outputPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $Output))
[IO.File]::WriteAllBytes($outputPath, [Convert]::FromBase64String($capture.data))

# Full-page capture temporarily expands the emulated CSS viewport. Restore the
# locked QA viewport before measuring scroll-linked behavior.
Send-CdpCommand $socket (++$id) 'Emulation.setDeviceMetricsOverride' @{
    width = $Width
    height = $Height
    deviceScaleFactor = 1
    mobile = $false
} | Out-Null

$inspection = Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
    expression = @'
(() => ({
  title: document.title,
  height: document.documentElement.scrollHeight,
  innerHeight: window.innerHeight,
  hero: Boolean(document.querySelector('.video-hero')),
  timeline: Boolean(document.querySelector('.timeline')),
  playhead: Boolean(document.querySelector('.tl-playhead')),
  bodyClass: document.body.className
  ,scrollWidth: document.documentElement.scrollWidth
  ,clientWidth: document.documentElement.clientWidth
  ,horizontalOverflow: document.documentElement.scrollWidth !== document.documentElement.clientWidth
  ,serviceHero: (() => { const el = document.querySelector('.svc-hero'); if (!el) return null; const r = el.getBoundingClientRect(); return { y:r.y, height:r.height, childMedia:Boolean(el.querySelector(':scope > .theme-hero-video')) }; })()
  ,serviceMediaParent: document.querySelector('.theme-hero-video')?.parentElement?.className || null
  ,mainChildren: Array.from(document.querySelector('main')?.children || []).slice(0,4).map(el => ({tag:el.tagName,className:el.className}))
  ,pricingOtherGeometry: (() => {
    const selectors = ['.cenik-packages > .container', '.cenik-other', '.cenik-other h2', '.cenik-other p'];
    const viewportCenter = document.documentElement.clientWidth / 2;
    return Object.fromEntries(selectors.map(selector => {
      const el = document.querySelector(selector);
      if (!el) return [selector, null];
      const r = el.getBoundingClientRect();
      return [selector, { left:r.left, width:r.width, center:r.left + r.width / 2, viewportCenter, delta:r.left + r.width / 2 - viewportCenter }];
    }));
  })()
  ,visualQa: (() => {
    const isVisible = element => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity) > .02 && rect.width > 2 && rect.height > 2;
    };
    const label = element => `${element.tagName.toLowerCase()}${element.id ? '#' + element.id : ''}${element.classList.length ? '.' + Array.from(element.classList).slice(0, 3).join('.') : ''}`;
    const box = element => {
      const rect = element.getBoundingClientRect();
      return { x:Math.round(rect.left), y:Math.round(rect.top), width:Math.round(rect.width), height:Math.round(rect.height), right:Math.round(rect.right), bottom:Math.round(rect.bottom) };
    };
    const candidates = Array.from(document.querySelectorAll('main h1,main h2,main h3,main p,main a,main button,main input,main select,main textarea,header a,header button,footer a,footer button')).filter(isVisible).slice(0, 450);
    const outsideViewport = candidates.filter(element => {
      const rect = element.getBoundingClientRect();
      return rect.left < -1 || rect.right > document.documentElement.clientWidth + 1;
    }).slice(0, 30).map(element => ({ node:label(element), box:box(element) }));
    const clipped = candidates.filter(element => {
      const style = getComputedStyle(element);
      const clipsX = ['hidden','clip','auto','scroll'].includes(style.overflowX) && element.scrollWidth > element.clientWidth + 1;
      const clipsY = ['hidden','clip','auto','scroll'].includes(style.overflowY) && element.scrollHeight > element.clientHeight + 1;
      return clipsX || clipsY;
    }).slice(0, 30).map(element => ({ node:label(element), box:box(element), clientWidth:element.clientWidth, scrollWidth:element.scrollWidth, clientHeight:element.clientHeight, scrollHeight:element.scrollHeight }));
    const smallText = candidates.filter(element => {
      const text = (element.textContent || element.value || '').trim();
      return text.length > 1 && parseFloat(getComputedStyle(element).fontSize) < 11;
    }).slice(0, 40).map(element => ({ node:label(element), text:(element.textContent || element.value || '').trim().slice(0, 80), fontSize:getComputedStyle(element).fontSize, box:box(element) }));
    const controls = Array.from(document.querySelectorAll('a.gb-button,button,input,select,textarea,[role="button"]')).filter(isVisible).slice(0, 120).map(element => ({ node:label(element), text:(element.textContent || element.value || element.getAttribute('aria-label') || '').trim().slice(0, 80), fontSize:getComputedStyle(element).fontSize, box:box(element) }));
    const overlaps = [];
    for (let firstIndex = 0; firstIndex < candidates.length && overlaps.length < 40; firstIndex++) {
      for (let secondIndex = firstIndex + 1; secondIndex < candidates.length && overlaps.length < 40; secondIndex++) {
        const first = candidates[firstIndex];
        const second = candidates[secondIndex];
        if (first.contains(second) || second.contains(first)) continue;
        const firstRect = first.getBoundingClientRect();
        const secondRect = second.getBoundingClientRect();
        const overlapWidth = Math.min(firstRect.right, secondRect.right) - Math.max(firstRect.left, secondRect.left);
        const overlapHeight = Math.min(firstRect.bottom, secondRect.bottom) - Math.max(firstRect.top, secondRect.top);
        if (overlapWidth > 4 && overlapHeight > 4 && overlapWidth * overlapHeight > Math.min(firstRect.width * firstRect.height, secondRect.width * secondRect.height) * .18) {
          overlaps.push({ first:label(first), second:label(second), area:Math.round(overlapWidth * overlapHeight), y:Math.round(Math.max(firstRect.top, secondRect.top)) });
        }
      }
    }
    const fixedElements = Array.from(document.querySelectorAll('body *')).filter(element => {
      if (!isVisible(element)) return false;
      const style = getComputedStyle(element);
      return (style.position === 'fixed' || style.position === 'sticky') && style.pointerEvents !== 'none';
    }).slice(0, 30);
    const fixedOcclusions = fixedElements.map(fixed => {
      const fixedRect = fixed.getBoundingClientRect();
      const covered = candidates.filter(element => {
        if (fixed.contains(element) || element.contains(fixed)) return false;
        const rect = element.getBoundingClientRect();
        const overlapWidth = Math.min(fixedRect.right, rect.right) - Math.max(fixedRect.left, rect.left);
        const overlapHeight = Math.min(fixedRect.bottom, rect.bottom) - Math.max(fixedRect.top, rect.top);
        return overlapWidth > 4 && overlapHeight > 4;
      }).slice(0, 20).map(label);
      return { node:label(fixed), position:getComputedStyle(fixed).position, box:box(fixed), covered };
    });
    const ancestorClipped = candidates.filter(element => {
      const rect = element.getBoundingClientRect();
      let ancestor = element.parentElement;
      while (ancestor && ancestor !== document.body) {
        const style = getComputedStyle(ancestor);
        if (['hidden','clip'].includes(style.overflowX) || ['hidden','clip'].includes(style.overflowY)) {
          const ancestorRect = ancestor.getBoundingClientRect();
          if (rect.left < ancestorRect.left - 1 || rect.right > ancestorRect.right + 1 || rect.top < ancestorRect.top - 1 || rect.bottom > ancestorRect.bottom + 1) return true;
        }
        ancestor = ancestor.parentElement;
      }
      return false;
    }).slice(0, 40).map(element => ({ node:label(element), box:box(element) }));
    const sectionGeometry = ['.gb-portfolio','.gb-pricing','.gb-reviews','.gb-blog','.gb-blog .hp-blog-grid','.gb-blog .hp-blog-card','.gb-blog .hp-blog-body','.gb-contact','.gb-contact>.container','.gb-contact-faq'].map(selector => {
      const element = document.querySelector(selector);
      if (!element) return { selector, missing:true };
      const style = getComputedStyle(element);
      return { selector, box:box(element), display:style.display, height:style.height, minHeight:style.minHeight, overflowX:style.overflowX, overflowY:style.overflowY, gridTemplateColumns:style.gridTemplateColumns, gridTemplateRows:style.gridTemplateRows };
    });
    return { outsideViewport, clipped, ancestorClipped, smallText, controls, overlaps, fixedOcclusions, sectionGeometry };
  })()
}))()
'@
    returnByValue = $true
}

$socket.Dispose()
if ($chromeProcess -and -not $chromeProcess.HasExited) {
    $chromeProcess.Kill()
}
[pscustomobject]@{
    Screenshot = $outputPath
    TimelineStartScreenshot = $timelineStartOutputPath
    TimelineActiveStartScreenshot = $timelineActiveStartOutputPath
    InteractionScreenshot = $interactionOutputPath
    NavigationScreenshot = $navigationOutputPath
    NavigationState = $navigationState
    ScrolledScreenshot = $scrolledOutputPath
    ScrolledState = $scrolledState
    ClickedScreenshot = $clickedOutputPath
    ClickedState = $clickedState
    Width = $Width
    Height = $pageHeight
    HeightStable = $heightStable
    LazyLoad = $lazyLoadResult.result.value
    Runtime = $inspection.result.value
    Interactions = $interaction.result.value
} | ConvertTo-Json -Depth 8
