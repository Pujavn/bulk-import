// public/js/importer.js

const base = window.location.origin;
const $ = (id) => document.getElementById(id);

// --- helpers ---
async function sha256(arrayBuffer) {
    const hash = await crypto.subtle.digest('SHA-256', arrayBuffer);
    return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
}
async function sha256Blob(blob) { return sha256(await blob.arrayBuffer()); }
function chunkify(file, chunkSize) {
    const chunks = []; let offset = 0, idx = 0;
    while (offset < file.size) {
        const end = Math.min(offset + chunkSize, file.size);
        chunks.push({ index: idx++, blob: file.slice(offset, end) });
        offset = end;
    }
    return chunks;
}

document.addEventListener('DOMContentLoaded', () => {

    const params = new URLSearchParams(window.location.search);
    const prePid = params.get('product_id');
    if (prePid && $('productId')) {
        $('productId').value = prePid;
        const logEl = $('uploadLog');
        if (logEl) {
            logEl.textContent += `Selected product #${prePid} — drop/choose an image to upload & attach.\n`;
        }
    }
    // ---------- CSV import ----------
    const csvBtn = $('csvBtn');
    const csvForm = $('csvForm');

    csvBtn?.addEventListener('click', async () => {
        const file = csvForm.querySelector('input[name="csv"]').files[0];
        const dir = csvForm.querySelector('input[name="images_dir"]').value.trim();
        if (!file) return;

        csvBtn.disabled = true;
        $('csvStats').innerHTML = '';
        $('csvErrors').innerHTML = '';
        $('csvResult').textContent = '';

        const fd = new FormData();
        fd.append('csv', file);
        if (dir) fd.append('images_dir', dir);

        const res = await fetch(`${base}/api/imports/products`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: fd
        });
        const json = await res.json();

        const mk = (k, v) => `<span class="stat"><strong>${k}:</strong> ${v ?? 0}</span>`;
        $('csvStats').innerHTML = [
            mk('Total', json.total), mk('Imported', json.imported), mk('Updated', json.updated),
            mk('Invalid', json.invalid), mk('Duplicates', json.duplicates)
        ].join(' ');

        $('csvErrors').innerHTML = (json.errors?.length)
            ? `<div class="muted">Errors:</div><ul>${json.errors.map(e => `<li>${e}</li>`).join('')}</ul>`
            : '';

        $('csvResult').textContent = JSON.stringify(json, null, 2);
        csvBtn.disabled = false;
    });

    // ---------- Chunked upload ----------
    const dz = $('dz');
    const fi = $('fileInput');

    dz?.addEventListener('click', () => fi.click());
    dz?.addEventListener('dragover', (e) => { e.preventDefault(); dz.classList.add('is-over'); });
    dz?.addEventListener('dragleave', () => dz.classList.remove('is-over'));
    dz?.addEventListener('drop', async (e) => {
        e.preventDefault(); dz.classList.remove('is-over');
        const file = e.dataTransfer.files[0];
        if (file) await startUpload(file);
    });
    fi?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) await startUpload(file);
    });

    async function startUpload(file) {
        const logEl = $('uploadLog'), bar = $('bar'), status = $('status'), lastLink = $('lastLink');
        logEl.textContent = ''; bar.value = 0; status.textContent = 'Hashing…'; lastLink.textContent = '';

        try {
            const fullSha = await sha256Blob(file);

            // INIT
            const chunkSize = 1024 * 1024; // 1MB
            const totalChunks = Math.ceil(file.size / chunkSize);
            const initRes = await fetch(`${base}/api/uploads/init`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    filename: file.name,
                    size: file.size,
                    total_chunks: totalChunks,
                    mime: file.type || 'application/octet-stream',
                    sha256: fullSha
                })
            });
            const initJson = await initRes.json();
            if (!initRes.ok) { logEl.textContent = 'INIT failed:\n' + JSON.stringify(initJson, null, 2); return; }
            const uploadId = initJson.upload_id;
            logEl.textContent += `INIT ok: ${uploadId}\n`;

            // CHUNKS
            const parts = chunkify(file, chunkSize);
            let sent = 0;
            for (const part of parts) {
                const fd = new FormData();
                fd.append('index', String(part.index));
                fd.append('sha256', await sha256Blob(part.blob));
                fd.append('blob', new File([part.blob], `chunk_${part.index}.bin`, { type: 'application/octet-stream' }));

                const r = await fetch(`${base}/api/uploads/${uploadId}/chunk`, {
                    method: 'POST', body: fd, headers: { 'Accept': 'application/json' }
                });
                if (!r.ok) { const j = await r.json(); logEl.textContent += 'CHUNK failed: ' + JSON.stringify(j) + '\n'; return; }
                sent++; bar.value = Math.round((sent / parts.length) * 100);
                status.textContent = `Uploaded ${sent}/${parts.length}`;
            }

            // COMPLETE
            const comp = await fetch(`${base}/api/uploads/${uploadId}/complete`, {
                method: 'POST', headers: { 'Accept': 'application/json' }
            });
            const compJson = await comp.json();
            if (!comp.ok) { logEl.textContent += 'COMPLETE failed: ' + JSON.stringify(compJson, null, 2); return; }
            logEl.textContent += 'COMPLETE ok: ' + JSON.stringify(compJson) + '\n';

            // Link to last uploaded original
            if (compJson.public_path) {
                lastLink.innerHTML = `<a href="/storage/${compJson.public_path}" target="_blank">View last uploaded original</a>`;
            }

            // ATTACH (optional)
            const pid = $('productId').value.trim();
            if (pid) {
                const att = await fetch(`${base}/api/products/${pid}/attach-upload/${uploadId}`, {
                    method: 'POST', headers: { 'Accept': 'application/json' }
                });
                const attJson = await att.json();
                logEl.textContent += `ATTACH → set product #${pid} primary_image_id = ${attJson.primary_image_id ?? '—'}\n`;
            } else {
                logEl.textContent += 'ATTACH skipped (no Product ID).\n';
            }

        } catch (err) {
            $('uploadLog').textContent += String(err) + '\n';
        }
    }
});
