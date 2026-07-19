# Legacy diagnostic tool only: primary visual QA uses Google Chrome and
# SCREENSHOT-WORKFLOW.md. Run this script only when a task explicitly requires
# verifying the VS Code Integrated Browser host path.
param(
    [string]$Name = '',
    [int]$Width = 941,
    [int]$Height = 1672,
    [decimal]$Dpr = 1,
    [int]$WaitAfterReloadMs = 5000
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($Name)) {
    $next = 0
    Get-ChildItem -LiteralPath $PSScriptRoot -File -Filter 'visual-progress-mobile-*.png' |
        ForEach-Object {
            if ($_.BaseName -match '^visual-progress-mobile-(\d+)') {
                $next = [Math]::Max($next, [int]$Matches[1] + 1)
            }
        }
    $timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $Name = 'visual-progress-mobile-{0:D2}-{1}.png' -f $next, $timestamp
}

Add-Type -AssemblyName UIAutomationClient
Add-Type -AssemblyName UIAutomationTypes
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public static class IntegratedBrowserCaptureWindow {
    [DllImport("user32.dll")] public static extern bool SetForegroundWindow(IntPtr handle);
    [DllImport("user32.dll")] public static extern bool ShowWindow(IntPtr handle, int command);
}
'@

function Find-VsCodeWindow {
    $root = [System.Windows.Automation.AutomationElement]::RootElement
    return $root.FindAll(
        [System.Windows.Automation.TreeScope]::Children,
        [System.Windows.Automation.Condition]::TrueCondition
    ) | Where-Object {
        $_.Current.Name -like '*seo-editor*Visual Studio Code*'
    } | Select-Object -First 1
}

function Find-Descendant($Parent, [scriptblock]$Predicate) {
    return $Parent.FindAll(
        [System.Windows.Automation.TreeScope]::Descendants,
        [System.Windows.Automation.Condition]::TrueCondition
    ) | Where-Object $Predicate | Select-Object -First 1
}

function Set-SpinnerValue($Window, [string]$Label, [string]$Value) {
    $element = Find-Descendant $Window {
        $_.Current.ControlType -eq [System.Windows.Automation.ControlType]::Spinner -and
        $_.Current.Name -eq $Label
    }
    if (-not $element) {
        throw "Integrated Browser control '$Label' was not found."
    }

    $pattern = [System.Windows.Automation.ValuePattern]$element.GetCurrentPattern(
        [System.Windows.Automation.ValuePattern]::Pattern
    )
    $pattern.SetValue($Value)
    $element.SetFocus()
    [System.Windows.Forms.SendKeys]::SendWait('{ENTER}')
    Start-Sleep -Milliseconds 180
}

$window = Find-VsCodeWindow
if (-not $window) {
    throw 'The seo-editor VS Code window was not found.'
}

[IntegratedBrowserCaptureWindow]::SetForegroundWindow(
    [IntPtr]$window.Current.NativeWindowHandle
) | Out-Null

$tab = Find-Descendant $window {
    $_.Current.ControlType -eq [System.Windows.Automation.ControlType]::TabItem -and
    $_.Current.Name -eq 'RB Videos & Photos (127.0.0.1:8766)'
}
if (-not $tab) {
    throw 'The Integrated Browser tab for 127.0.0.1:8766 was not found.'
}

$selection = [System.Windows.Automation.SelectionItemPattern]$tab.GetCurrentPattern(
    [System.Windows.Automation.SelectionItemPattern]::Pattern
)
$selection.Select()
Start-Sleep -Milliseconds 350

Set-SpinnerValue $window 'Viewport width' ([string]$Width)
Set-SpinnerValue $window 'Viewport height' ([string]$Height)
Set-SpinnerValue $window 'Device pixel ratio' ([string]$Dpr)

$zoom = Find-Descendant $window {
    $_.Current.ControlType -eq [System.Windows.Automation.ControlType]::ComboBox -and
    $_.Current.Name -eq 'Zoom factor'
}
if (-not $zoom) {
    throw 'The Integrated Browser zoom control was not found.'
}
$zoomValue = [System.Windows.Automation.ValuePattern]$zoom.GetCurrentPattern(
    [System.Windows.Automation.ValuePattern]::Pattern
)
if ($zoomValue.Current.Value -ne '100%') {
    $expand = [System.Windows.Automation.ExpandCollapsePattern]$zoom.GetCurrentPattern(
        [System.Windows.Automation.ExpandCollapsePattern]::Pattern
    )
    $expand.Expand()
    Start-Sleep -Milliseconds 180
    $fullZoom = Find-Descendant $window {
        $_.Current.ControlType -eq [System.Windows.Automation.ControlType]::ListItem -and
        $_.Current.Name -eq '100%'
    }
    if (-not $fullZoom) {
        throw 'The 100% Integrated Browser zoom option was not found.'
    }
    $invoke = [System.Windows.Automation.InvokePattern]$fullZoom.GetCurrentPattern(
        [System.Windows.Automation.InvokePattern]::Pattern
    )
    $invoke.Invoke()
    Start-Sleep -Milliseconds 350
}

$reload = Find-Descendant $window {
    $_.Current.ControlType -eq [System.Windows.Automation.ControlType]::Button -and
    $_.Current.Name -like '*Reload*'
}
if (-not $reload) {
    throw 'The Integrated Browser Reload or Hard Reload action was not found.'
}
$reloadPattern = [System.Windows.Automation.InvokePattern]$reload.GetCurrentPattern(
    [System.Windows.Automation.InvokePattern]::Pattern
)
$reloadPattern.Invoke()
Start-Sleep -Milliseconds $WaitAfterReloadMs

$root = [System.Windows.Automation.AutomationElement]::RootElement
$devTools = $root.FindAll(
    [System.Windows.Automation.TreeScope]::Children,
    [System.Windows.Automation.Condition]::TrueCondition
) | Where-Object {
    $_.Current.Name -like 'Developer Tools - http://127.0.0.1:8766/*'
} | Select-Object -First 1

if (-not $devTools) {
    $devToolsButton = Find-Descendant $window {
        $_.Current.ControlType -eq [System.Windows.Automation.ControlType]::Button -and
        $_.Current.Name -eq 'Developer Tools (F12)'
    }
    if ($devToolsButton) {
        $devToolsToggle = [System.Windows.Automation.TogglePattern]$devToolsButton.GetCurrentPattern(
            [System.Windows.Automation.TogglePattern]::Pattern
        )
        if ($devToolsToggle.Current.ToggleState -ne [System.Windows.Automation.ToggleState]::On) {
            $devToolsToggle.Toggle()
        }
    } else {
        # The narrow Browser toolbar can move the button into an inaccessible
        # overflow menu. F12 is the advertised equivalent on the selected tab.
        [System.Windows.Forms.SendKeys]::SendWait('{F12}')
    }
    Start-Sleep -Milliseconds 1200

    $devTools = $root.FindAll(
        [System.Windows.Automation.TreeScope]::Children,
        [System.Windows.Automation.Condition]::TrueCondition
    ) | Where-Object {
        $_.Current.Name -like 'Developer Tools - http://127.0.0.1:8766/*'
    } | Select-Object -First 1
}
if (-not $devTools) {
    throw 'Integrated Browser Developer Tools did not open.'
}

[IntegratedBrowserCaptureWindow]::ShowWindow(
    [IntPtr]$devTools.Current.NativeWindowHandle,
    5
) | Out-Null
[IntegratedBrowserCaptureWindow]::SetForegroundWindow(
    [IntPtr]$devTools.Current.NativeWindowHandle
) | Out-Null
Start-Sleep -Milliseconds 300

[System.Windows.Forms.SendKeys]::SendWait('{ESC}')
$customizeButton = $devTools.FindAll(
    [System.Windows.Automation.TreeScope]::Descendants,
    [System.Windows.Automation.PropertyCondition]::new(
        [System.Windows.Automation.AutomationElement]::ControlTypeProperty,
        [System.Windows.Automation.ControlType]::Button
    )
) | Where-Object {
    $_.Current.Name -eq 'Customize and control DevTools'
} | Select-Object -Last 1
if (-not $customizeButton) {
    throw 'The DevTools Customize menu button was not found.'
}
([System.Windows.Automation.InvokePattern]$customizeButton.GetCurrentPattern(
    [System.Windows.Automation.InvokePattern]::Pattern
)).Invoke()
Start-Sleep -Milliseconds 250

$runCommand = $root.FindAll(
    [System.Windows.Automation.TreeScope]::Descendants,
    [System.Windows.Automation.PropertyCondition]::new(
        [System.Windows.Automation.AutomationElement]::ControlTypeProperty,
        [System.Windows.Automation.ControlType]::MenuItem
    )
) | Where-Object {
    $_.Current.Name -like 'Run command*'
} | Select-Object -First 1
if (-not $runCommand) {
    throw 'The DevTools Run command menu item was not found.'
}
([System.Windows.Automation.InvokePattern]$runCommand.GetCurrentPattern(
    [System.Windows.Automation.InvokePattern]::Pattern
)).Invoke()
Start-Sleep -Milliseconds 250

$quickOpen = Find-Descendant $devTools {
    $_.Current.ControlType -eq [System.Windows.Automation.ControlType]::Edit -and
    $_.Current.Name -eq 'Quick open prompt'
}
if (-not $quickOpen) {
    throw 'The DevTools command prompt did not open.'
}
$quickOpenValue = [System.Windows.Automation.ValuePattern]$quickOpen.GetCurrentPattern(
    [System.Windows.Automation.ValuePattern]::Pattern
)
$quickOpenValue.SetValue('')
$quickOpen.SetFocus()
foreach ($character in 'screenshot'.ToCharArray()) {
    [System.Windows.Forms.SendKeys]::SendWait([string]$character)
    Start-Sleep -Milliseconds 50
}
Start-Sleep -Milliseconds 650

$captureCommand = Find-Descendant $devTools {
    $_.Current.ControlType -eq [System.Windows.Automation.ControlType]::ListItem -and
    $_.Current.Name -like 'Capture full size screenshot*'
}
if (-not $captureCommand) {
    throw 'The DevTools full-size screenshot command was not found.'
}

$started = Get-Date
$capturePattern = [System.Windows.Automation.InvokePattern]$captureCommand.GetCurrentPattern(
    [System.Windows.Automation.InvokePattern]::Pattern
)
$capturePattern.Invoke()

$downloads = Join-Path $HOME 'Downloads'
$source = $null
$dimensions = $null
$destination = Join-Path $PSScriptRoot $Name
for ($attempt = 0; $attempt -lt 20 -and -not $source; $attempt++) {
    Start-Sleep -Milliseconds 250
    $candidates = Get-ChildItem -LiteralPath $downloads -File |
        Where-Object {
            $_.LastWriteTime -gt $started.AddSeconds(-1) -and
            $_.Extension -in @('.png', '.tmp')
        } |
        Sort-Object LastWriteTime -Descending

    foreach ($candidate in $candidates) {
        try {
            $image = [System.Drawing.Image]::FromFile($candidate.FullName)
            try {
                $dimensions = '{0}x{1}' -f $image.Width, $image.Height
            } finally {
                $image.Dispose()
            }
            Copy-Item -LiteralPath $candidate.FullName -Destination $destination -Force
            $source = $candidate
            break
        } catch {
            # The browser may still be writing the temporary PNG; retry shortly.
        }
    }
}
if (-not $source) {
    throw 'DevTools did not expose a readable fresh PNG download.'
}

[pscustomobject]@{
    Path = $destination
    Dimensions = $dimensions
    Viewport = "${Width}x${Height}"
    Dpr = $Dpr
    CapturedAt = (Get-Item -LiteralPath $destination).LastWriteTime
}
