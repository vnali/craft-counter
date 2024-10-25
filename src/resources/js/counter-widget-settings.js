(function () {
    $(document).on('change', '.widget.new .max-online-widget div[data-attribute="showChart"] .lightswitch', function() {
        var isEnabled = $(this).find('input').val();
        if (isEnabled) {
            $('.widget.new .max-online-widget div[data-attribute="showVisitor"]').css('display', 'block');
        } else {
            $('.widget.new .max-online-widget div[data-attribute="showVisitor"] .lightswitch').data('lightswitch').turnOff(true);
            $('.widget.new .max-online-widget div[data-attribute="showVisitor"]').css('display', 'none');
        }
    });

    $(document).on('change', '.widget.new .visits-recent-widget div[data-attribute="showChart"] .lightswitch', function() {
        var isEnabled = $(this).find('input').val();
        if (isEnabled) {
            $('.widget.new .visits-recent-widget div[data-attribute="showVisitor"]').css('display', 'block');
            $('.widget.new .visits-recent-widget div[data-attribute=preferredIntervalInput]').css('display', 'block');
        } else {
            $('.widget.new .visits-recent-widget div[data-attribute="showVisitor"] .lightswitch').data('lightswitch').turnOff(true);
            $('.widget.new .visits-recent-widget div[data-attribute="showVisitor"]').css('display', 'none');
            $('.widget.new .visits-recent-widget div[data-attribute=preferredIntervalInput]').css('display', 'none');
        }
    });

    $(document).on('change', '.widget.new .visits-widget div[data-attribute="showChart"] .lightswitch', function() {
        var isEnabled = $(this).find('input').val();
        if (isEnabled) {
            $('.widget.new .visits-widget div[data-attribute="showVisitor"]').css('display', 'block');
            if ($('.widget.new .visits-widget [data-date-range]').val() == 'today') {
                $('.widget.new .visits-widget div[data-attribute=preferredIntervalInput]').css('display', 'block');
            }
        } else {
            $('.widget.new .visits-widget div[data-attribute="showVisitor"] .lightswitch').data('lightswitch').turnOff(true);
            $('.widget.new .visits-widget div[data-attribute="showVisitor"]').css('display', 'none');
            $('.widget.new .visits-widget div[data-attribute=preferredIntervalInput]').css('display', 'none');
        }
    });

    $(document).on('change', '.widget.new .visitors-widget div[data-attribute="showChart"] .lightswitch', function() {
        var isEnabled = $(this).find('input').val();
        if (isEnabled) {
            $('.widget.new .visitors-widget div[data-attribute="visitorTypeInput"]').css('display', 'block');
            $('.widget.new .visitors-widget div[data-attribute="preferredIntervalInput"]').css('display', 'block');
        } else {
            $('.widget.new .visitors-widget div[data-attribute="visitorTypeInput"]').css('display', 'none');
            $('.widget.new .visitors-widget div[data-attribute="preferredIntervalInput"]').css('display', 'none');
        }
    });
})();