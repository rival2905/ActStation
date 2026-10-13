// resources/js/scheduler/composables/useApi.js

export function useApi(apiBase = '') {
    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        // Fallback to cookie XSRF-TOKEN if present
        const match = document.cookie.match(new RegExp('(^|;\\s*)(?:XSRF-TOKEN)=([^;]*)'));
        return match ? decodeURIComponent(match[2]) : '';
    };

    const normalizeUrl = (endpoint) => {
        if (endpoint.startsWith('http://') || endpoint.startsWith('https://')) {
            return endpoint;
        }
        const base = (apiBase || '').replace(/\/+$/, '');
        const path = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
        return `${base}${path}`;
    };

    const request = async (endpoint, options = {}) => {
        const url = normalizeUrl(endpoint);
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...(options.headers || {}),
        };

        let body = options.body;
        if (body && typeof body === 'object' && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        const res = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers,
            body,
        });

        if (!res.ok) {
            let errorData = {};
            try {
                errorData = await res.json();
            } catch {
                errorData = { message: res.statusText || `HTTP ${res.status}` };
            }
            const err = new Error(errorData.message || 'Terjadi kesalahan sistem.');
            err.status = res.status;
            err.message = errorData.message || 'Terjadi kesalahan sistem.';
            err.errors = errorData.errors || {};
            throw err;
        }

        const contentType = res.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            return await res.json();
        }
        return await res.text();
    };

    return {
        get: (endpoint, params = {}) => {
            const search = new URLSearchParams(params).toString();
            const url = search ? `${endpoint}${endpoint.includes('?') ? '&' : '?'}${search}` : endpoint;
            return request(url, { method: 'GET' });
        },
        post: (endpoint, body = {}) => request(endpoint, { method: 'POST', body }),
        put: (endpoint, body = {}) => request(endpoint, { method: 'PUT', body }),
        del: (endpoint, body = null) => request(endpoint, { method: 'DELETE', body }),
    };
}
