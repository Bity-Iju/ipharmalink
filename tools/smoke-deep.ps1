# Deep smoke test: every GET route in the route table, per role.
# Usage:  .\tools\smoke-deep.ps1
$base = 'http://localhost:8000'

function Get-Token([Microsoft.PowerShell.Commands.WebRequestSession]$s, [string]$path) {
    $r = Invoke-WebRequest -Uri "$base$path" -UseBasicParsing -WebSession $s -TimeoutSec 20
    if ($r.Content -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }
    return $null
}

function Login([string]$door, [string]$email, [string]$password = 'Password123!') {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $t = Get-Token $s $door
    if (-not $t) { Write-Host "  no CSRF token at $door" -ForegroundColor Red; return $null }
    try {
        Invoke-WebRequest -Uri "$base$door" -Method Post -Body @{ _token = $t; identifier = $email; password = $password } `
            -UseBasicParsing -WebSession $s -TimeoutSec 20 -MaximumRedirection 5 | Out-Null
    }
    catch {
        Write-Host "  login failed for $email : $($_.Exception.Message)" -ForegroundColor Red
        return $null
    }
    return $s
}

function Test-Paths([Microsoft.PowerShell.Commands.WebRequestSession]$s, [string[]]$paths, [string]$label) {
    Write-Host ""
    Write-Host "=== $label" -ForegroundColor Cyan
    $fail = 0
    foreach ($p in $paths) {
        try {
            $r = Invoke-WebRequest -Uri "$base$p" -UseBasicParsing -WebSession $s -TimeoutSec 25
            if ($r.StatusCode -ne 200) { Write-Host ("  {0,-46} {1}" -f $p, $r.StatusCode) -ForegroundColor Yellow; $fail++ }
        }
        catch {
            $code = $_.Exception.Response.StatusCode.value__
            Write-Host ("  {0,-46} ERR {1}" -f $p, $code) -ForegroundColor Red
            $fail++
        }
    }
    Write-Host ("  -> {0} failure(s) out of {1}" -f $fail, $paths.Count) -ForegroundColor $(if ($fail) { 'Red' } else { 'Green' })
    return $fail
}

$total = 0

# ---- public storefront -----------------------------------------------------
$pub = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$total += Test-Paths $pub @(
    '/', '/about', '/how-it-works', '/pharmacies', '/products',
    '/categories', '/search', '/contact', '/faq', '/terms', '/privacy',
    '/refund-policy', '/delivery-policy', '/how-it-works',
    '/login', '/register', '/forgot-password'
) 'Public pages'

# ---- customer --------------------------------------------------------------
$c = Login '/login' 'customer@demo.ng'
if ($c) {
    $total += Test-Paths $c @(
        '/account', '/account/profile', '/account/orders', '/account/addresses',
        '/account/payments', '/account/wishlist', '/account/reviews',
        '/account/notifications', '/cart', '/checkout'
    ) 'Customer'
}

# ---- pharmacy --------------------------------------------------------------
$p = Login '/pharmacy/login' 'owner@healthplus.ng'
if ($p) {
    $total += Test-Paths $p @(
        '/pharmacy/dashboard', '/pharmacy/products', '/pharmacy/products/create',
        '/pharmacy/inventory', '/pharmacy/inventory/low-stock', '/pharmacy/inventory/expiring',
        '/pharmacy/inventory/history', '/pharmacy/orders', '/pharmacy/orders/new',
        '/pharmacy/prescriptions', '/pharmacy/deliveries', '/pharmacy/customers',
        '/pharmacy/staff', '/pharmacy/reports', '/pharmacy/wallet', '/pharmacy/payouts',
        '/pharmacy/profile', '/pharmacy/settings', '/pharmacy/reviews', '/pharmacy/notifications'
    ) 'Pharmacy'
}

# ---- admin (full table, including every report) ----------------------------
$a = Login '/admin/login' 'admin@ipharmalink.ng'
if ($a) {
    $total += Test-Paths $a @(
        '/admin/dashboard',
        '/admin/pharmacies', '/admin/pharmacies/pending', '/admin/products',
        '/admin/categories', '/admin/brands', '/admin/customers', '/admin/staff',
        '/admin/delivery-personnel', '/admin/delivery-personnel/create',
        '/admin/orders', '/admin/orders/pending', '/admin/orders/processing',
        '/admin/orders/delivered', '/admin/orders/cancelled',
        '/admin/payments', '/admin/refunds', '/admin/refunds/create',
        '/admin/commissions', '/admin/wallets', '/admin/payouts', '/admin/coupons',
        '/admin/deliveries', '/admin/prescriptions', '/admin/reviews',
        '/admin/reports', '/admin/reports/sales', '/admin/reports/orders',
        '/admin/reports/pharmacies', '/admin/reports/customers', '/admin/reports/products',
        '/admin/reports/commissions', '/admin/reports/payments', '/admin/reports/deliveries',
        '/admin/reports/refunds', '/admin/reports/inventory',
        '/admin/banners', '/admin/pages', '/admin/pages/create',
        '/admin/faqs', '/admin/contact-messages', '/admin/notifications',
        '/admin/audit-logs', '/admin/settings', '/admin/payment-settings',
        '/admin/delivery-settings', '/admin/email-settings', '/admin/security-settings'
    ) 'Admin'
}

# ---- delivery --------------------------------------------------------------
$d = Login '/login' 'rider@demo.ng'
if ($d) {
    $total += Test-Paths $d @(
        '/delivery/dashboard', '/delivery/orders', '/delivery/history', '/delivery/profile'
    ) 'Delivery'
}

Write-Host ""
if ($total -eq 0) {
    Write-Host "ALL GREEN - no failures." -ForegroundColor Green
}
else {
    Write-Host "$total failure(s) remaining." -ForegroundColor Red
}
