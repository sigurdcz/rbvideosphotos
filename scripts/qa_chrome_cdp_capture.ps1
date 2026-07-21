param(
    [string]$Url = 'http://localhost:8000/?theme_preview=premium-photo',
    [string]$Output = 'premium-photo-cdp.png',
    [int]$Width = 1280,
    [int]$Height = 800,
    [int]$Port = 9333,
    [string]$TimelineStartOutput = '',
    [string]$TimelineActiveStartOutput = '',
    [string]$InteractionOutput = '',
    [switch]$OpenNavigation,
    [string]$NavigationOutput = '',
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
        '--force-device-scale-factor=1',
        "--remote-debugging-port=$Port",
        "--user-data-dir=$profilePath",
        "--window-size=$Width,$Height",
        '--disable-gpu',
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
Start-Sleep -Seconds 4

if ($OpenNavigation) {
    Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
        expression = @'
(async () => {
  const toggle = document.querySelector('.nav-toggle');
  if (!toggle) return false;
  if (!document.body.classList.contains('nav-open')) toggle.click();
  await new Promise(resolve => setTimeout(resolve, 350));
  return document.body.classList.contains('nav-open');
})()
'@
        returnByValue = $true
        awaitPromise = $true
    } | Out-Null
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

Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
    expression = '(async () => { window.scrollTo(0, 0); await new Promise(resolve => setTimeout(resolve, 100)); })()'
    awaitPromise = $true
} | Out-Null

$metrics = Send-CdpCommand $socket (++$id) 'Page.getLayoutMetrics'
$pageHeight = [Math]::Ceiling($metrics.cssContentSize.height)
$capture = Send-CdpCommand $socket (++$id) 'Page.captureScreenshot' @{
    format = 'png'
    fromSurface = $true
    captureBeyondViewport = $true
    clip = @{ x = 0; y = 0; width = $Width; height = $pageHeight; scale = 1 }
}

$outputPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $Output))
[IO.File]::WriteAllBytes($outputPath, [Convert]::FromBase64String($capture.data))

# captureBeyondViewport can temporarily expand the visual viewport. Restore the
# requested viewport before measuring scroll-linked behavior.
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
    Width = $Width
    Height = $pageHeight
    Runtime = $inspection.result.value
    Interactions = $interaction.result.value
} | ConvertTo-Json -Depth 8
