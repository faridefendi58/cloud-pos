Loading = {
    show: function () {
        $('.loader').find('.mdl-spinner').addClass('is-active');
        $('.loader').show();
    },
    hide: function () {
        $('.loader').hide();
        $('.loader').find('.mdl-spinner').removeClass('is-active');
    }
}
var toast = {
    // elem contains the snackbar element. Change the id or use a 'querySelector'
    elem  : document.querySelector('.mdl-js-snackbar'),
    init  : false,
    queue : []
}

function showToast(msg) {
    if (!toast.init) {
        toast.queue.push(msg);
        return;
    }

    var data = {message: msg};
    toast.elem.MaterialSnackbar.showSnackbar(data);
}

window.addEventListener('load', function() {
    toast.init = true;
    while (toast.queue.length > 0) showToast(toast.queue.shift());
    toast.queue = [];
});