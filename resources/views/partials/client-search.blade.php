{{-- Buscador de cliente compartido: #clientSearch, #clientId, #clientLabel, #clientResults y, si existe, #address --}}
<script>
(function () {
    var input = document.getElementById('clientSearch');
    var hidden = document.getElementById('clientId');
    var label = document.getElementById('clientLabel');
    var results = document.getElementById('clientResults');
    var address = document.getElementById('address');
    var url = @json(route('clients.search'));
    var timer;

    input.addEventListener('input', function () {
        hidden.value = '';
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { results.classList.add('hidden'); return; }
        timer = setTimeout(function () {
            fetch(url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (items) {
                    if (input.value.trim() !== q) return;
                    results.innerHTML = '';
                    if (!items.length) {
                        results.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">Sin resultados</div>';
                    }
                    items.forEach(function (item) {
                        var div = document.createElement('div');
                        div.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-blue-50';
                        div.textContent = item.label + (item.phone ? ' · ' + item.phone : '');
                        div.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            input.value = item.name;
                            label.value = item.name;
                            hidden.value = item.id;
                            if (address && !address.value && item.address) address.value = item.address;
                            results.classList.add('hidden');
                        });
                        results.appendChild(div);
                    });
                    results.classList.remove('hidden');
                });
        }, 250);
    });
    input.addEventListener('blur', function () { setTimeout(function () { results.classList.add('hidden'); }, 200); });
})();
</script>
