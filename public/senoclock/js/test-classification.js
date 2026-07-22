(function () {
    'use strict';

    const { loginApiUrl, classificationApiUrl, auth } = window.SENOCLOCK_TEST || {};

    const loginBtn = document.getElementById('btn-login');
    const loginResult = document.getElementById('login-result');
    const classifyForm = document.getElementById('classification-form');
    const classifyBtn = document.getElementById('btn-classify');
    const responseStatus = document.getElementById('response-status');
    const responseOutput = document.getElementById('response-output');

    let accessToken = null;

    function toNumber(value) {
        const trimmed = String(value).trim();

        if (trimmed === '') {
            return null;
        }

        const num = Number(trimmed);

        return Number.isNaN(num) ? trimmed : num;
    }

    function parseFieldValue(value, type) {
        const trimmed = String(value).trim();

        if (trimmed === '') {
            return null;
        }

        if (type === 'number') {
            return toNumber(value);
        }

        return trimmed;
    }

    function showLoginResult(success, message, extra) {
        loginResult.classList.remove('hidden', 'success', 'error');
        loginResult.classList.add(success ? 'success' : 'error');
        loginResult.textContent = message;

        if (extra) {
            loginResult.textContent += '\n' + JSON.stringify(extra, null, 2);
        }
    }

    function showResponse(success, statusCode, data) {
        responseStatus.classList.remove('hidden', 'success', 'error');
        responseStatus.classList.add(success ? 'success' : 'error');
        responseStatus.textContent = success
            ? `Success (${statusCode})`
            : `Failed (${statusCode})`;

        responseOutput.textContent = JSON.stringify(data, null, 2);
    }

    function setLoading(button, loading) {
        button.disabled = loading;
        button.dataset.originalText = button.dataset.originalText || button.textContent;
        button.textContent = loading ? 'Please wait…' : button.dataset.originalText;
    }

    function buildClassificationPayload() {
        const payload = {};

        classifyForm.querySelectorAll('[data-payload-key]').forEach(function (input) {
            const key = input.dataset.payloadKey;
            const type = input.dataset.type || 'string';
            const value = parseFieldValue(input.value, type);

            if (value !== null && value !== '') {
                payload[key] = value;
            }
        });

        const bloodPressure = {};
        let hasBloodPressure = false;

        classifyForm.querySelectorAll('[data-bp-key]').forEach(function (input) {
            const key = input.dataset.bpKey;
            const value = parseFieldValue(input.value, input.dataset.type || 'number');

            if (value !== null && value !== '') {
                bloodPressure[key] = value;
                hasBloodPressure = true;
            }
        });

        if (hasBloodPressure) {
            payload['Blood Pressure'] = bloodPressure;
        }

        return payload;
    }

    async function parseJsonResponse(response) {
        const data = await response.json().catch(() => ({}));

        return { ok: response.ok, status: response.status, data };
    }

    function corsErrorMessage() {
        return 'Request blocked by CORS. The browser must call SenoClock directly — ensure the API allows your origin, or use the server proxy routes.';
    }

    async function loginToSenoclock() {
        if (!auth?.email || !auth?.password) {
            return {
                ok: false,
                status: 0,
                data: {
                    message: 'SenoClock credentials are not configured in server .env.',
                    api_url: loginApiUrl,
                },
            };
        }

        let response;

        try {
            response = await fetch(loginApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    email: auth.email,
                    password: auth.password,
                }),
            });
        } catch (err) {
            return {
                ok: false,
                status: 0,
                data: {
                    message: err.message.includes('Failed to fetch') ? corsErrorMessage() : err.message,
                    api_url: loginApiUrl,
                },
            };
        }

        const result = await parseJsonResponse(response);
        accessToken = result.data.access_token || null;

        return result;
    }

    loginBtn.addEventListener('click', async function () {
        setLoading(loginBtn, true);

        try {
            const { ok, status, data } = await loginToSenoclock();

            if (ok && accessToken) {
                showLoginResult(true, 'Login successful.', {
                    api_url: loginApiUrl,
                    access_token_preview: accessToken.substring(0, 20) + '...',
                    user: data.user ?? null,
                });
            } else {
                showLoginResult(false, data.message || data.detail || 'Login failed.', {
                    api_url: loginApiUrl,
                    status: status,
                    body: data,
                });
            }
        } catch (err) {
            showLoginResult(false, err.message);
        } finally {
            setLoading(loginBtn, false);
        }
    });

    classifyForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        setLoading(classifyBtn, true);

        try {
            if (!accessToken) {
                const login = await loginToSenoclock();
                if (!login.ok || !accessToken) {
                    showResponse(false, login.status || 0, {
                        success: false,
                        message: login.data?.message || 'Failed to obtain access token.',
                        api_url: loginApiUrl,
                        body: login.data,
                    });
                    return;
                }
            }

            const payload = buildClassificationPayload();
            let response;

            try {
                response = await fetch(classificationApiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + accessToken,
                    },
                    body: JSON.stringify(payload),
                });
            } catch (err) {
                showResponse(false, 0, {
                    success: false,
                    message: err.message.includes('Failed to fetch') ? corsErrorMessage() : err.message,
                    api_url: classificationApiUrl,
                });
                return;
            }

            const { ok, status, data } = await parseJsonResponse(response);

            showResponse(ok, status, ok ? {
                success: true,
                message: 'Classification completed successfully.',
                api_url: classificationApiUrl,
                data: data,
            } : {
                success: false,
                message: 'Classification request failed.',
                api_url: classificationApiUrl,
                status: status,
                body: data,
                payload: payload,
            });
        } catch (err) {
            showResponse(false, 0, { message: err.message });
        } finally {
            setLoading(classifyBtn, false);
        }
    });
})();
