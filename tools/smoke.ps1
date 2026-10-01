# Local development smoke test.
# Signs in as each demo role and requests the key pages, reporting status codes.
# Usage:  powershell -File tools/smoke.ps1

$base = 'http://localhost:8000'

function New-Session {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    return $s
}

function Get-Token([Microsoft.PowerShell.Commands.WebRequestSession]$s, [string]$path) {
    $r = Invoke-WebRequest -Uri "$base$path" -UseBasicParsing -WebSession $s -TimeoutSec 20
    if ($r.Content -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }
    return $null
}

function Login([string]$door, [string]$email, [string]$password = 'Password123!') {
    $s = New-Session
    $token = Get-Token $s $door
    if (-not $token) { Write-Host "  could not read CSRF token from $door" -ForegroundColor Red; return $null }
    $body = @{ _token = $token; identifier = $email; password = $password }
    $r = Invoke-WebRequest -Uri "$base$door" -Method Post -Body $body -UseBasicParsing -WebSession $s -TimeoutSec 20 -MaximumRedirection 5
    return $s
}

function Test-Pages([Microsoft.PowerShell.Commands.WebRequestSession]$s, [string[]]$paths, [string]$label) {
    Write-Host ""
    Write-Host "=== $label" -ForegroundColor Cyan
    foreach ($p in $paths) {
        try {
            $r = Invoke-WebRequest -Uri "$base$p" -UseBasicParsing -WebSession $s -TimeoutSec 20
            "{0,-44} {1}  {2} bytes" -f $p, $r.StatusCode, $r.Content.Length
        } catch {
            "{0,-44} ERR {1}" -f $p, $_.Exception.Message
        }
    }
}

# ---- Customer -------------------------------------------------------------
$c = Login '/login' 'customer@demo.ng'
if ($c) { Test-Pages $c @('/account','/account/profile','/account/orders','/account/addresses','/account/wishlist','/account/notifications','/account/reviews','/cart','/checkout') 'Customer' }

# ---- Pharmacy -------------------------------------------------------------
$p = Login '/pharmacy/login' 'owner@healthplus.ng'
if ($p) { Test-Pages $p @('/pharmacy/dashboard','/pharmacy/products','/pharmacy/products/create','/pharmacy/inventory','/pharmacy/inventory/low-stock','/pharmacy/inventory/expiring','/pharmacy/inventory/history','/pharmacy/orders','/pharmacy/orders/new','/pharmacy/prescriptions','/pharmacy/deliveries','/pharmacy/customers','/pharmacy/staff','/pharmacy/reports','/pharmacy/wallet','/pharmacy/payouts','/pharmacy/profile','/pharmacy/settings','/pharmacy/reviews','/pharmacy/notifications') 'Pharmacy' }

# ---- Admin ----------------------------------------------------------------
$a = Login '/admin/login' 'admin@ipharmalink.ng'
if ($a) { Test-Pages $a @('/admin/dashboard','/admin/pharmacies','/admin/pharmacies/pending','/admin/products','/admin/categories','/admin/brands','/admin/customers','/admin/staff','/admin/delivery-personnel','/admin/orders','/admin/payments','/admin/refunds','/admin/commissions','/admin/payouts','/admin/coupons','/admin/reviews','/admin/banners','/admin/pages','/admin/faqs','/admin/contact-messages','/admin/notifications','/admin/audit-logs','/admin/reports','/admin/settings','/admin/payment-settings','/admin/delivery-settings','/admin/email-settings','/admin/security-settings') 'Admin' }

# ---- Delivery -------------------------------------------------------------
$d = Login '/login' 'rider@demo.ng'
if ($d) { Test-Pages $d @('/delivery/dashboard','/delivery/orders','/delivery/history','/delivery/profile') 'Delivery' }
