<meta name="application-name" content="{{ config('app.name', 'EMS Infra ERP') }}">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'EMS Infra ERP') }}">
<meta name="pwa-push-enabled" content="{{ config('pwa.push.enabled') ? '1' : '0' }}">
<meta name="pwa-vapid-public-key" content="{{ (string) config('pwa.push.vapid_public_key', '') }}">
<meta name="pwa-sync-enabled" content="{{ config('pwa.background_sync.enabled') ? '1' : '0' }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/pwa-180.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/pwa-192.png') }}">
@php
    $erpPwaConfig = [
        'pushEnabled' => (bool) config('pwa.push.enabled', true),
        'vapidPublicKey' => (string) config('pwa.push.vapid_public_key', ''),
        'syncEnabled' => (bool) config('pwa.background_sync.enabled', true),
        'criticalFormPathPrefixes' => array_values(config('pwa.background_sync.critical_form_path_prefixes', [])),
    ];
@endphp
<script>
window.__ERP_PWA = {!! json_encode($erpPwaConfig, JSON_UNESCAPED_SLASHES) !!};
</script>
