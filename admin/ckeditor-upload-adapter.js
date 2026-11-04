(function (window) {
    'use strict';

    function CkeditorUploadAdapter(loader) {
        this.loader = loader;
        this.xhr = null;
        this.uploadUrl = '/admin/blog-upload.php?context=blog';
    }

    CkeditorUploadAdapter.prototype.upload = function () {
        var loader = this.loader;
        var uploadUrl = this.uploadUrl;
        var xhr = this.xhr = new XMLHttpRequest();
        var formData = new FormData();
        var csrfInput = document.querySelector('input[name="csrf_token"]');
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfInput ? csrfInput.value : (csrfMeta ? csrfMeta.content : '');

        formData.append('csrf_token', csrfToken);

        return loader.file.then(function (file) {
            formData.append('upload', file, file.name);
            return new Promise(function (resolve, reject) {
                xhr.open('POST', uploadUrl, true);
                xhr.responseType = 'json';

                xhr.addEventListener('error', function () {
                    reject('Не удалось загрузить изображение. Проверьте соединение с сервером.');
                });
                xhr.addEventListener('abort', function () {
                    reject();
                });
                xhr.addEventListener('load', function () {
                    var response = xhr.response;
                    if (!response && xhr.responseText) {
                        try {
                            response = JSON.parse(xhr.responseText);
                        } catch (error) {
                            response = null;
                        }
                    }

                    if (xhr.status >= 200 && xhr.status < 300 && response && response.uploaded && response.url) {
                        resolve({ default: response.url });
                        return;
                    }

                    var message = response && response.error && response.error.message
                        ? response.error.message
                        : 'Сервер не принял изображение (код ' + xhr.status + ').';
                    reject(message);
                });

                xhr.upload.addEventListener('progress', function (event) {
                    if (event.lengthComputable) {
                        loader.uploadTotal = event.total;
                        loader.uploaded = event.loaded;
                    }
                });

                xhr.send(formData);
            });
        });
    };

    CkeditorUploadAdapter.prototype.abort = function () {
        if (this.xhr) {
            this.xhr.abort();
        }
    };

    window.CkeditorUploadAdapterPlugin = function (editor) {
        var uploadUrl = editor.config.get('ckUploadUrl') || '/admin/blog-upload.php?context=blog';
        editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
            var adapter = new CkeditorUploadAdapter(loader);
            adapter.uploadUrl = uploadUrl;
            return adapter;
        };
    };
}(window));
