console.log("CLUES serch cargado correctamente 🚀");

const inputClues = document.getElementById('cluesSearchInput');
const selectClues = document.getElementById('cluesSelect');

let timeout = null;

inputClues?.addEventListener('input', function () {
    clearTimeout(timeout);

    timeout = setTimeout(async () => {
        const q = inputClues.value.trim();
        if (q.length < 2) return;

        const params = new URLSearchParams({
            catalogo: document.getElementById('catalogoInput').value,
            cubo: document.getElementById('cuboInput').value,
            estado: 'HIDALGO',
            limit: 5,
            prefix: '',
            q: q
        });

        const response = await fetch(`/api/vacunas/clues/search?${params}`);
        const data = await response.json();

        selectClues.innerHTML = '';

        if (data.ok && data.items.length) {
            data.items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.label;
                selectClues.appendChild(option);
            });
        }
    }, 300); // debounce 300ms
});
