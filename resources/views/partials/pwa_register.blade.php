{{-- Service worker (online-first: pages are never cached, /offline.html is shown when there is no connection) --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
</script>
