(function ($) {
    'use strict';

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
            return true;
        }

        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            var successful = document.execCommand('copy');
            document.body.removeChild(textarea);
            return successful;
        } catch (err) {
            document.body.removeChild(textarea);
            return false;
        }
    }

    $(document).on('click', '.flygit-copy', function (event) {
        event.preventDefault();
        var targetId = $(this).data('target');
        var target = document.getElementById(targetId);
        if (!target) {
            return;
        }

        var text = target.innerText || target.textContent;
        var copied = copyToClipboard(text);

        if (copied) {
            var originalText = $(this).text();
            $(this).text(flygitAdmin.copySuccess || 'Copied');
            var button = $(this);
            setTimeout(function () {
                button.text(originalText);
            }, 2000);
        }
    });
})(jQuery);
