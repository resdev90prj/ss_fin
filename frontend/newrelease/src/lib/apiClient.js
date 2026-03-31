function absoluteBaseUrl() {
  return new URL(import.meta.env.BASE_URL, window.location.origin);
}

export function getApiBase() {
  const url = new URL('../api/', absoluteBaseUrl());
  return url.pathname.endsWith('/') ? url.pathname.slice(0, -1) : url.pathname;
}

export function getLegacyBase() {
  const url = new URL('../', absoluteBaseUrl());
  return url.pathname;
}

function buildUrl(path, data) {
  const apiBase = getApiBase();
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  const url = new URL(`${apiBase}${normalizedPath}`, window.location.origin);

  if (data && Object.keys(data).length > 0) {
    Object.entries(data).forEach(([key, value]) => {
      if (value === '' || value === null || value === undefined || value === false) {
        return;
      }

      url.searchParams.set(key, String(value));
    });
  }

  return url;
}

export async function apiRequest(path, options = {}) {
  const { method = 'GET', data, signal } = options;
  const upperMethod = method.toUpperCase();
  const url = buildUrl(path, upperMethod === 'GET' ? data : undefined);

  const requestOptions = {
    method: upperMethod,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
    },
    signal,
  };

  if (upperMethod !== 'GET') {
    requestOptions.headers['Content-Type'] = 'application/json';
    requestOptions.body = JSON.stringify(data ?? {});
  }

  const response = await fetch(url, requestOptions);

  let payload = null;
  try {
    payload = await response.json();
  } catch (error) {
    payload = null;
  }

  if (!response.ok || !payload?.success) {
    const apiError = new Error(payload?.message || 'Falha ao comunicar com a API.');
    apiError.status = response.status;
    apiError.errors = Array.isArray(payload?.errors) ? payload.errors : [];
    throw apiError;
  }

  return payload;
}

