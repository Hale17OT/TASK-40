import './bootstrap';

// Device fingerprint traits collection
document.addEventListener('DOMContentLoaded', () => {
    window.__deviceTraits = {
        width: screen.width,
        height: screen.height,
        colorDepth: screen.colorDepth,
        pixelRatio: window.devicePixelRatio || 1,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    };

    // Send screen trait headers on all axios requests (API calls)
    window.axios.interceptors.request.use((config) => {
        config.headers['X-Screen-Width'] = String(window.__deviceTraits.width);
        config.headers['X-Screen-Height'] = String(window.__deviceTraits.height);
        config.headers['X-Screen-Color-Depth'] = String(window.__deviceTraits.colorDepth);
        return config;
    });

    // Send screen trait headers on Livewire requests
    if (typeof Livewire !== 'undefined') {
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ options }) => {
                options.headers = options.headers || {};
                options.headers['X-Screen-Width'] = String(window.__deviceTraits.width);
                options.headers['X-Screen-Height'] = String(window.__deviceTraits.height);
                options.headers['X-Screen-Color-Depth'] = String(window.__deviceTraits.colorDepth);
            });
        });
    }

    // Time sync heartbeat (every 60 seconds)
    const syncTime = async () => {
        try {
            const response = await fetch('/api/time-sync');
            const data = await response.json();
            const serverTime = data.server_time;
            const clientTime = Math.floor(Date.now() / 1000);
            const drift = Math.abs(serverTime - clientTime);

            if (drift > 30) {
                const banner = document.getElementById('time-drift-banner');
                if (banner) {
                    banner.classList.remove('hidden');
                    banner.textContent = `Clock drift detected (${drift}s). Payment operations may be affected.`;
                }
            }
        } catch (e) {
            // Silently ignore sync failures
        }
    };

    syncTime();
    setInterval(syncTime, 60000);
});
