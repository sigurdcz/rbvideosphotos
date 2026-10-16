param(
    [string]$BaseUrl = 'http://localhost:8000',
    [string]$OutputDir = 'storage/qa/visual-audit-2026-08-11',
    [int]$Width = 1920,
    [int]$Height = 1080,
    [int]$Port = 9444,
    [int]$WaitMilliseconds = 1400,
    [int]$MaxAttempts = 3,
    [string]$RoutePattern = '',
    [string]$ChromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
)

$ErrorActionPreference = 'Stop'

function Send-CdpCommand {
    param([Net.WebSockets.ClientWebSocket]$Socket, [int]$Id, [string]$Method, [hashtable]$Params = @{})
    $payload = @{ id = $Id; method = $Method; params = $Params } | ConvertTo-Json -Depth 15 -Compress
    $bytes = [Text.Encoding]::UTF8.GetBytes($payload)
    $Socket.SendAsync([ArraySegment[byte]]::new($bytes), [Net.WebSockets.WebSocketMessageType]::Text, $true, [Threading.CancellationToken]::None).GetAwaiter().GetResult()
    while ($true) {
        $stream = [IO.MemoryStream]::new()
        do {
            $buffer = [byte[]]::new(65536)
            $result = $Socket.ReceiveAsync([ArraySegment[byte]]::new($buffer), [Threading.CancellationToken]::None).GetAwaiter().GetResult()
            $stream.Write($buffer, 0, $result.Count)
        } while (-not $result.EndOfMessage)
        $message = [Text.Encoding]::UTF8.GetString($stream.ToArray()) | ConvertFrom-Json
        if ($message.id -eq $Id) {
            if ($message.error) { throw "CDP $Method selhalo: $($message.error | ConvertTo-Json -Compress)" }
            return $message.result
        }
    }
}

if (-not (Test-Path -LiteralPath $ChromePath)) { throw "Chrome nebyl nalezen: $ChromePath" }
$outputPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $OutputDir))
New-Item -ItemType Directory -Force -Path $outputPath | Out-Null

[xml]$sitemap = (Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl/sitemap.xml" -TimeoutSec 20).Content
$runtimeBase = [Uri]$BaseUrl
$urls = @($sitemap.urlset.url | ForEach-Object {
    $listed = [Uri][string]$_.loc
    [Uri]::new($runtimeBase, $listed.PathAndQuery).AbsoluteUri
})
if ($RoutePattern -ne '') { $urls = @($urls | Where-Object { ([Uri]$_).AbsolutePath -match $RoutePattern }) }
if ($urls.Count -eq 0) { throw 'Sitemap neobsahuje zadne URL.' }

$profilePath = Join-Path ([IO.Path]::GetTempPath()) "seo-editor-visual-audit-$([Guid]::NewGuid().ToString('N'))"
New-Item -ItemType Directory -Path $profilePath | Out-Null
$chrome = Start-Process -FilePath $ChromePath -WindowStyle Hidden -PassThru -ArgumentList @(
    '--headless=new', '--force-device-scale-factor=1', "--remote-debugging-port=$Port",
    "--user-data-dir=$profilePath", "--window-size=$Width,$Height", '--hide-scrollbars', 'about:blank'
)

try {
    $endpoint = "http://127.0.0.1:$Port/json/list"
    $targets = $null
    for ($attempt = 0; $attempt -lt 40; $attempt++) {
        try { $targets = Invoke-RestMethod -Uri $endpoint -TimeoutSec 1; break } catch { Start-Sleep -Milliseconds 250 }
    }
    $target = @($targets).Where({ $_.type -eq 'page' }) | Select-Object -First 1
    if (-not $target) { throw 'Chrome CDP neposkytl strankovy target.' }

    $socket = $null
    $lastSocketError = $null
    for ($attempt = 0; $attempt -lt 10; $attempt++) {
        try {
            $socket = [Net.WebSockets.ClientWebSocket]::new()
            $socket.ConnectAsync([Uri]$target.webSocketDebuggerUrl, [Threading.CancellationToken]::None).GetAwaiter().GetResult() | Out-Null
            $lastSocketError = $null
            break
        } catch {
            $lastSocketError = $_
            if ($socket) { $socket.Dispose() }
            Start-Sleep -Milliseconds 300
            $targets = Invoke-RestMethod -Uri $endpoint -TimeoutSec 1
            $target = @($targets).Where({ $_.type -eq 'page' }) | Select-Object -First 1
        }
    }
    if ($lastSocketError) { throw $lastSocketError }
    $id = 0
    Send-CdpCommand $socket (++$id) 'Page.enable' | Out-Null
    Send-CdpCommand $socket (++$id) 'Runtime.enable' | Out-Null
    Send-CdpCommand $socket (++$id) 'Network.enable' | Out-Null
    Send-CdpCommand $socket (++$id) 'Network.setCacheDisabled' @{ cacheDisabled = $true } | Out-Null
    Send-CdpCommand $socket (++$id) 'Emulation.setDeviceMetricsOverride' @{ width=$Width; height=$Height; deviceScaleFactor=1; mobile=$false } | Out-Null

    $results = @()
    for ($index = 0; $index -lt $urls.Count; $index++) {
        $url = $urls[$index]
        $accepted = $false
        for ($captureAttempt = 1; $captureAttempt -le $MaxAttempts; $captureAttempt++) {
            Send-CdpCommand $socket (++$id) 'Page.navigate' @{ url=$url } | Out-Null
            Start-Sleep -Milliseconds $WaitMilliseconds
            Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{ expression='(async()=>{for(let y=0;y<document.documentElement.scrollHeight;y+=700){scrollTo(0,y);await new Promise(r=>setTimeout(r,45))}await Promise.race([Promise.all([...document.images].map(i=>i.complete?Promise.resolve():new Promise(resolve=>{i.addEventListener("load",resolve,{once:true});i.addEventListener("error",resolve,{once:true})}))),new Promise(resolve=>setTimeout(resolve,5000))]);scrollTo(0,0);await new Promise(r=>setTimeout(r,350))})()'; awaitPromise=$true } | Out-Null

            $inspection = Send-CdpCommand $socket (++$id) 'Runtime.evaluate' @{
            returnByValue=$true
            expression=@'
(() => {
  const visible = el => { const s=getComputedStyle(el), r=el.getBoundingClientRect(); return s.display!=='none' && s.visibility!=='hidden' && +s.opacity>0.02 && r.width>2 && r.height>2; };
  const rect = el => { const r=el.getBoundingClientRect(); return {x:r.left,y:r.top+scrollY,w:r.width,h:r.height,right:r.right,bottom:r.bottom+scrollY}; };
  const label = el => `${el.tagName.toLowerCase()}${el.id?'#'+el.id:''}${el.classList.length?'.'+[...el.classList].slice(0,3).join('.'):''}`;
  const nodes=[...document.querySelectorAll('h1,h2,h3,p,a,button,input,textarea,select,[role="button"],.card,.panel')].filter(visible).slice(0,700);
  const overlaps=[];
  for(let i=0;i<nodes.length;i++) for(let j=i+1;j<nodes.length;j++) {
    const a=nodes[i],b=nodes[j]; if(a.contains(b)||b.contains(a)) continue;
    const x=rect(a),y=rect(b),iw=Math.min(x.x+x.w,y.x+y.w)-Math.max(x.x,y.x),ih=Math.min(x.y+x.h,y.y+y.h)-Math.max(x.y,y.y);
    if(iw>8&&ih>8&&iw*ih>Math.min(x.w*x.h,y.w*y.h)*0.18) overlaps.push({a:label(a),b:label(b),area:Math.round(iw*ih),atY:Math.round(Math.max(x.y,y.y))});
    if(overlaps.length>=40) break;
  }
  const blocks=[...document.querySelectorAll('main section,main>div,footer')].filter(visible).map(el=>{const r=rect(el),s=getComputedStyle(el);return {node:label(el),y:Math.round(r.y),h:Math.round(r.h),w:Math.round(r.w),background:s.backgroundColor};});
  const gaps=[]; for(let i=1;i<blocks.length;i++){const gap=blocks[i].y-(blocks[i-1].y+blocks[i-1].h);if(gap>350)gaps.push({after:blocks[i-1].node,before:blocks[i].node,pixels:Math.round(gap)});}
  const heroTitle=document.querySelector('.gb-hero-story-title');
  const heroTitleLines=heroTitle?[...heroTitle.childNodes].flatMap(node=>{
    if(node.nodeType===Node.TEXT_NODE&&!node.textContent.trim())return [];
    const range=document.createRange();range.selectNodeContents(node);return [...range.getClientRects()].map(r=>({text:node.textContent.trim(),x:Math.round(r.x),y:Math.round(r.y+scrollY),w:Math.round(r.width),h:Math.round(r.height),bottom:Math.round(r.bottom+scrollY)}));
  }):[];
  const heroTitleStyle=heroTitle?getComputedStyle(heroTitle):null;
  const heroLayout=[['header','.gb-header'],['hero','.hp-hero'],['seoTitle','.gb-hero-seo-title'],['storyTitle','.gb-hero-story-title'],['subtitle','.hero-sub'],['cta','.hero-cta-wrap'],['benefits','.gb-hero-benefits'],['nextSection','.hp-hero+section']].map(([name,selector])=>{const el=document.querySelector(selector);return el&&visible(el)?{name,...rect(el)}:null}).filter(Boolean);
  const heroStack=heroLayout.filter(item=>['seoTitle','storyTitle','subtitle','cta','benefits'].includes(item.name));
  const heroCollisions=[];for(let i=1;i<heroStack.length;i++){const previous=heroStack[i-1],current=heroStack[i];if(current.y<previous.bottom-1)heroCollisions.push({previous:previous.name,current:current.name,pixels:Math.round(previous.bottom-current.y)});}
  const headerBox=heroLayout.find(item=>item.name==='header'),heroBox=heroLayout.find(item=>item.name==='hero'),nextBox=heroLayout.find(item=>item.name==='nextSection');
  const heroBoundaries={headerToHero:headerBox&&heroBox?Math.round(heroBox.y-headerBox.bottom):null,heroToNext:heroBox&&nextBox?Math.round(nextBox.y-heroBox.bottom):null};
  const shell=document.querySelector('.gb-shell'),shellStyle=shell?getComputedStyle(shell):null;
  const themeCssApplied=!!(shellStyle&&getComputedStyle(document.documentElement).getPropertyValue('--gb-paper').trim()!=='');
  const requiredImages=[...document.images].filter(i=>i.hasAttribute('src')&&i.getAttribute('src').trim()!==''||i.hasAttribute('srcset')&&i.getAttribute('srcset').trim()!=='');
  const brokenImages=requiredImages.filter(i=>!i.complete||i.naturalWidth===0).map(i=>({src:i.currentSrc||i.getAttribute('src')||i.getAttribute('srcset'),complete:i.complete,naturalWidth:i.naturalWidth}));
  return {title:document.title,statusTheme:document.body.className,width:innerWidth,height:document.documentElement.scrollHeight,scrollWidth:document.documentElement.scrollWidth,horizontalOverflow:document.documentElement.scrollWidth>document.documentElement.clientWidth+1,themeCssApplied,brokenImages,heroTitle:heroTitle?{rect:rect(heroTitle),fontSize:heroTitleStyle.fontSize,lineHeight:heroTitleStyle.lineHeight,lines:heroTitleLines}:null,heroLayout,heroCollisions,heroBoundaries,overlaps,gaps,blocks:blocks.filter(b=>b.h>500&&(b.background==='rgb(255, 255, 255)'||b.background==='rgb(0, 0, 0)')).slice(0,30)};
})()
'@
        }
            $audit = $inspection.result.value
            $invalidReasons = @()
            if (-not $audit.themeCssApplied) { $invalidReasons += 'Golden Bash theme CSS nebylo aplikovano' }
            if (@($audit.brokenImages).Count -gt 0) { $invalidReasons += ("{0} obrazku neni kompletne nacteno: {1}" -f @($audit.brokenImages).Count, (($audit.brokenImages | ConvertTo-Json -Compress -Depth 4) -join '')) }
            if ([int]$audit.height -gt 15000) { $invalidReasons += "abnormalni vyska $($audit.height) px" }
            if ($invalidReasons.Count -gt 0) {
                Write-Warning ("[{0}/{1}] pokus {2}/{3} neplatny pro {4}: {5}" -f ($index+1),$urls.Count,$captureAttempt,$MaxAttempts,$url,($invalidReasons -join '; '))
                if ($captureAttempt -lt $MaxAttempts) { Start-Sleep -Milliseconds 600 }
                continue
            }

            $metrics = Send-CdpCommand $socket (++$id) 'Page.getLayoutMetrics'
            $pageHeight = [Math]::Max(1, [Math]::Ceiling($metrics.cssContentSize.height))
            $slug = ([Uri]$url).AbsolutePath.Trim('/'); if ($slug -eq '') { $slug='home' }
            $safeSlug = $slug -replace '[^a-zA-Z0-9-]', '-'
            $fileName = 'desktop-{0:D2}-{1}.png' -f ($index+1), $safeSlug
            $capture = Send-CdpCommand $socket (++$id) 'Page.captureScreenshot' @{ format='png'; fromSurface=$true; captureBeyondViewport=$true; clip=@{x=0;y=0;width=$Width;height=$pageHeight;scale=1} }
            [IO.File]::WriteAllBytes((Join-Path $outputPath $fileName), [Convert]::FromBase64String($capture.data))
            $row = [ordered]@{index=$index+1;url=$url;screenshot=$fileName;attempt=$captureAttempt;capturedAt=(Get-Date).ToString('s');inspection=$audit}
            $results += [pscustomobject]$row
            Write-Output ("[{0}/{1}] {2} -> {3} (pokus {4})" -f ($index+1),$urls.Count,$url,$fileName,$captureAttempt)
            $accepted = $true
            break
        }
        if (-not $accepted) { throw "Capture $url selhal po $MaxAttempts pokusech kvuli nekompletnim assetum nebo geometrii." }
        Send-CdpCommand $socket (++$id) 'Emulation.setDeviceMetricsOverride' @{ width=$Width; height=$Height; deviceScaleFactor=1; mobile=$false } | Out-Null
    }
    $results | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath (Join-Path $outputPath 'audit-data.json') -Encoding utf8
    $socket.Dispose()
} finally {
    if ($chrome -and -not $chrome.HasExited) { $chrome.Kill() }
}

Write-Output ("Hotovo: {0} screenshotu v {1}" -f $urls.Count,$outputPath)
