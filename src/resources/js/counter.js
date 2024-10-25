(function () {
    if (window.supportOutdatedBrowsers) {
        $.ajax({
            method: "GET",
            url: window.sessionInfoUrl,
            dataType: 'json',
            success: function (result) {
                var data = {};
                data[result.csrfTokenName] = result.csrfTokenValue;
                data['pageUrl'] = decodeURIComponent(window.location.href);
                $.ajax({
                    method: "POST",
                    url: window.counterUrl + "?t=" + new Date().getTime(),
                    data: data,
                    dataType: 'json',
                });
            }
        });
    } else {
        fetch(window.sessionInfoUrl, {
            headers: {
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(result => {
            var data = {};
            data[result.csrfTokenName] = result.csrfTokenValue;
            data['pageUrl'] = window.location.href,
            fetch(window.counterUrl + "?t=" + new Date().getTime(), {
                method: "POST",
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            });
        });
    } 
})();