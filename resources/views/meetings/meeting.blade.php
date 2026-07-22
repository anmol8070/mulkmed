<!DOCTYPE html>
<html>
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Meeting</title>
    <style>
        html, body, #jitsi-container {
            height: 100%;
            margin: 0;
            padding: 0;
            background: #000;
        }
    </style>
</head>
<body>
<div id="jitsi-container"></div>

@php
    $endTimeIso = null;
    if (isset($end_time)) {
        $endTimeIso = $end_time instanceof \Carbon\Carbon
            ? $end_time->toIso8601String()
            : (string) $end_time;
    }
@endphp

<script src="https://{{ env('JITSI_DOMAIN', 'vc.reapmind.com') }}/external_api.js"></script>
<script>
    const domain = @json(env('JITSI_DOMAIN', 'vc.reapmind.com'));
    const hiddenDomain = @json(env('JITSI_HIDDEN_DOMAIN', 'recorder.vc.reapmind.com'));
    const autoRecordEnabled = @json(filter_var(env('JITSI_RECORDING_ENABLED', true), FILTER_VALIDATE_BOOLEAN));
    const autoRecordModeratorOnly = @json(filter_var(env('JITSI_AUTO_RECORD_MODERATOR_ONLY', true), FILTER_VALIDATE_BOOLEAN));
    const roomName = @json($roomId ?? $room ?? 'Appointment');
    const jwt = @json($jwt ?? null);
    const endTimeIso = @json($endTimeIso);

const options = {
        roomName: roomName,
    parentNode: document.querySelector('#jitsi-container'),
    width: '100%',
    height: '100%',
    configOverwrite: {
            disableDeepLinking: true,
            fileRecordingsEnabled: true,
            hiddenDomain: hiddenDomain,
            enableWelcomePage: false,
            prejoinPageEnabled: false,
    },
    interfaceConfigOverwrite: {
            MOBILE_APP_PROMO: false,
        },
};

    if (jwt) {
        options.jwt = jwt;
    }

const api = new JitsiMeetExternalAPI(domain, options);

    let recordingStarted = false;
    const maxRecordAttempts = 10;

    function startFileRecording(attempt = 0) {
        if (!autoRecordEnabled || recordingStarted) {
            return;
        }

        if (autoRecordModeratorOnly && !api.isModerator()) {
            if (attempt < maxRecordAttempts) {
                setTimeout(() => startFileRecording(attempt + 1), 500);
            }
            return;
        }

        recordingStarted = true;
        api.executeCommand('startRecording', { mode: 'file' });
    }

    api.addEventListener('videoConferenceJoined', () => startFileRecording());

    api.addEventListener('participantRoleChanged', (event) => {
        if (event.role === 'moderator') {
            startFileRecording();
        }
    });

    api.addEventListener('participantJoined', () => {
        if (autoRecordModeratorOnly && api.isModerator()) {
            startFileRecording();
        }
    });

    api.addEventListener('recordingStatusChanged', (payload) => {
        if (payload && payload.on) {
            recordingStarted = true;
        }
    });

    api.addEventListener('readyToClose', () => {
        if (recordingStarted) {
            api.executeCommand('stopRecording', 'file');
        }
    });

    let meetingEndTimer = null;

    if (endTimeIso) {
        const endTime = new Date(endTimeIso);

    function checkMeetingEnd() {
        const now = new Date();
        const remainingMs = endTime - now;

        if (remainingMs <= 0) {
                if (recordingStarted) {
                    api.executeCommand('stopRecording', 'file');
                }
            api.executeCommand('hangup');
            alert('Meeting time is over');
                if (meetingEndTimer) {
                    clearInterval(meetingEndTimer);
                    meetingEndTimer = null;
        }
    }
        }

    checkMeetingEnd();
        meetingEndTimer = setInterval(checkMeetingEnd, 60 * 1000);
    }
</script>
</body>
</html>
