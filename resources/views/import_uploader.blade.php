<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Import + Chunked Upload (Dev)</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto; padding:24px;}
    .card{border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-bottom:24px;}
    .dropzone{border:2px dashed #9ca3af; border-radius:12px; padding:24px; text-align:center;}
    .muted{color:#6b7280}
    .row{display:flex; gap:16px; align-items:center}
    progress{width:240px}
    .mono{font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace}
  </style>
</head>
<body>
  <h1>Bulk Import + Chunked Upload</h1>

  <!-- CSV Import -->
  <div class="card">
    <h2>CSV Import</h2>
    <form id="csvForm">
      <div class="row">
        <label>CSV:</label>
        <input type="file" name="csv" accept=".csv,text/csv" required />
      </div>
      <div class="row" style="margin-top:8px">
        <label>Images dir (under storage/app):</label>
        <input type="text" name="images_dir" value="import_images" />
      </div>
      <button type="submit" style="margin-top:12px">Import CSV</button>
    </form>
    <pre id="csvResult" class="mono"></pre>
  </div>

  <!-- Chunked Upload -->
  <div class="card">
    <h2>Image Upload (chunked, resumable)</h2>
    <div class="dropzone" id="dz">Drop image here or click to choose</div>
    <input type="file" id="fileInput" accept="image/*" style="display:none" />
    <div class="row" style="margin-top:12px">
      <label>Attach to Product ID:</label>
      <input id="productId" type="number" min="1" placeholder="e.g. 1" />
    </div>
    <div class="row" style="margin-top:12px">
      <progress id="bar" max="100" value="0"></progress>
      <span id="status" class="muted"></span>
    </div>
    <pre id="uploadLog" class="mono"></pre>
  </div>

<script>
const base = location.origin;

async function sha256(arrayBuffer) {
  const hash = await crypto.subtle.digest('SHA-256', arrayBuffer);
  const bytes = Array.from(new Uint8Array(hash));
  return bytes.map(b => b.toString(16).padStart(2,'0')).join('');
}

function chunkify(file, chunkSize) {
  const chunks = [];
  let offset = 0, idx = 0;
  while (offset < file.size) {
    const end = Math.min(offset + chunkSize, file.size);
    chunks.push({index: idx++, blob: file.slice(offset, end)});
    offset = end;
  }
  return chunks;
}

// CSV import
document.getElementById('csvForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const res = await fetch(`${base}/api/imports/products`, { method: 'POST', body: fd, headers: { 'Accept':'application/json' }});
  const json = await res.json();
  document.getElementById('csvResult').textContent = JSON.stringify(json, null, 2);
});

// Chunked upload UI
const dz = document.getElementById('dz');
const fi = document.getElementById('fileInput');
dz.addEventListener('click', ()=> fi.click());
dz.addEventListener('dragover', (e)=>{ e.preventDefault(); dz.style.background='#f9fafb'; });
dz.addEventListener('dragleave', ()=> dz.style.background='transparent');
dz.addEventListener('drop', async (e)=> {
  e.preventDefault(); dz.style.background='transparent';
  const file = e.dataTransfer.files[0];
  if (file) await startUpload(file);
});
fi.addEventListener('change', async (e)=> {
  const file = e.target.files[0];
  if (file) await startUpload(file);
});

async function startUpload(file) {
  const log = document.getElementById('uploadLog');
  const bar = document.getElementById('bar');
  const status = document.getElementById('status');
  log.textContent = ''; bar.value = 0; status.textContent = 'Hashing...';

  const buf = await file.arrayBuffer();
  const fullSha = await sha256(buf);
  const chunkSize = 512 * 1024; // 512KB
  const parts = chunkify(file, chunkSize);
  const totalChunks = parts.length;

  // init
  const initRes = await fetch(`${base}/api/uploads/init`, {
    method: 'POST',
    headers: { 'Content-Type':'application/json', 'Accept':'application/json' },
    body: JSON.stringify({
      filename: file.name,
      size: file.size,
      total_chunks: totalChunks,
      mime: file.type || 'application/octet-stream',
      sha256: fullSha
    })
  });
  const initJson = await initRes.json();
  if (!initRes.ok) { log.textContent = 'INIT failed:\n' + JSON.stringify(initJson,null,2); return; }
  const uploadId = initJson.upload_id;
  log.textContent += `INIT ok: ${uploadId}\n`;

  // chunks
  let sent = 0;
  for (const part of parts) {
    const chunkBuf = await part.blob.arrayBuffer();
    const chunkSha = await sha256(chunkBuf);
    const fd = new FormData();
    fd.append('index', String(part.index));
    fd.append('sha256', chunkSha);
    fd.append('blob', new File([part.blob], `chunk_${part.index}.bin`, { type: 'application/octet-stream' }));

    const r = await fetch(`${base}/api/uploads/${uploadId}/chunk`, {
      method: 'POST', body: fd, headers: { 'Accept':'application/json' }
    });
    if (!r.ok) { const j = await r.json(); log.textContent += 'CHUNK failed: '+JSON.stringify(j)+'\n'; return; }
    sent++;
    bar.value = Math.round((sent/totalChunks)*100);
    status.textContent = `Uploaded ${sent}/${totalChunks}`;
  }

  // complete
  const comp = await fetch(`${base}/api/uploads/${uploadId}/complete`, { method: 'POST', headers: { 'Accept':'application/json' }});
  const compJson = await comp.json();
  if (!comp.ok) { log.textContent += 'COMPLETE failed: '+JSON.stringify(compJson,null,2); return; }
  log.textContent += 'COMPLETE ok: ' + JSON.stringify(compJson) + '\n';

  // attach (optional)
  const pid = document.getElementById('productId').value.trim();
  if (pid) {
    const att = await fetch(`${base}/api/products/${pid}/attach-upload/${uploadId}`, { method: 'POST', headers: { 'Accept':'application/json' }});
    const attJson = await att.json();
    log.textContent += 'ATTACH: ' + JSON.stringify(attJson) + '\n';
  }
}
</script>
</body>
</html>
