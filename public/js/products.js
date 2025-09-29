// public/js/products.js
const base = window.location.origin;

document.addEventListener('DOMContentLoaded', () => {
    const prodRows = document.getElementById('prodRows');
    const pageInfo = document.getElementById('pageInfo');
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    const qInput = document.getElementById('q');
    const onlyNo = document.getElementById('onlyNoImage');
    const perPage = document.getElementById('perPage');
    const loadBtn = document.getElementById('prodSearchBtn');

    if (!prodRows) return;

    let state = { current: 1, last: 1, q: '', onlyNo: false, perPage: 10 };

    async function load(page = 1) {
        state.q = (qInput.value || '').trim();
        state.onlyNo = !!onlyNo.checked;
        state.perPage = Math.min(50, Math.max(1, parseInt(perPage.value || '10', 10)));

        const params = new URLSearchParams({
            page: String(page),
            per_page: String(state.perPage),
            q: state.q,
            only_no_image: state.onlyNo ? '1' : '0',
        });

        const res = await fetch(`${base}/api/products?` + params.toString(), { headers: { 'Accept': 'application/json' } });
        const json = await res.json();

        state.current = json.current;
        state.last = json.last;

        prodRows.innerHTML = '';
        json.data.forEach(p => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
        <td class="td">${p.id}</td>
        <td class="td">${p.sku}</td>
        <td class="td">${p.name}</td>
        <td class="td">${p.price.toFixed(2)}</td>
        <td class="td">
          ${p.has_image
                    ? '<span class="badge badge-ok">Yes</span>'
                    : '<span class="badge badge-no">No</span>'}
        </td>
        <td class="td td-actions">
          <a href="/import-uploader?product_id=${p.id}" class="btn btn-small">Attach image</a>
        </td>
      `;
            prodRows.appendChild(tr);
        });

        pageInfo.textContent = `Page ${state.current} of ${state.last} • ${json.total} total`;
        prevBtn.disabled = state.current <= 1;
        nextBtn.disabled = state.current >= state.last;
    }

    loadBtn?.addEventListener('click', () => load(1));
    prevBtn?.addEventListener('click', () => state.current > 1 && load(state.current - 1));
    nextBtn?.addEventListener('click', () => state.current < state.last && load(state.current + 1));

    load(1);
});
