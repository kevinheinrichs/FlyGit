(function ($) {
    'use strict';

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text)
                .then(function () {
                    return true;
                })
                .catch(function () {
                    return false;
                });
        }

        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        var successful = false;

        try {
            successful = document.execCommand('copy');
        } catch (err) {
            successful = false;
        }

        document.body.removeChild(textarea);

        return successful;
    }

    $(document).on('click', '.flygit-copy', function (event) {
        event.preventDefault();
        var targetId = $(this).data('target');
        var target = document.getElementById(targetId);
        if (!target) {
            return;
        }

        var text = target.innerText || target.textContent;
        var button = $(this);
        var originalText = button.text();

        var handleResult = function (copied) {
            if (!copied) {
                return;
            }

            button.text(flygitAdmin.copySuccess || 'Copied');

            setTimeout(function () {
                button.text(originalText);
            }, 2000);
        };

        var result = copyToClipboard(text);

        if (result && typeof result.then === 'function') {
            result.then(handleResult);
        } else {
            handleResult(result);
        }
    });
})(jQuery);
