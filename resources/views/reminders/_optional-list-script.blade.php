{{-- Behaviour for every [data-optional-list] on the page; include once, after the lists. --}}
<script>
    // "+" adds a row to an optional list (up to its maximum); "Remove" drops one.
    document.querySelectorAll('[data-optional-list]').forEach(function (list) {
        var rows = list.querySelector('[data-rows]');
        var template = list.querySelector('template');
        var addButton = list.querySelector('[data-add]');
        var max = Number(list.dataset.max);
        var nextKey = Number(list.dataset.nextKey);

        function refresh() {
            addButton.hidden = rows.querySelectorAll('[data-row]').length >= max;
        }

        addButton.addEventListener('click', function () {
            var html = template.innerHTML.replace(/__KEY__/g, String(nextKey++));
            rows.insertAdjacentHTML('beforeend', html);
            rows.lastElementChild.querySelector('input').focus();
            refresh();
        });

        rows.addEventListener('click', function (event) {
            if (event.target.closest('[data-remove]')) {
                event.target.closest('[data-row]').remove();
                refresh();
                addButton.focus();
            }
        });
    });
</script>
