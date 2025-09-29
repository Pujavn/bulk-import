<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Import + Chunked Upload (Dev)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="{{ asset('css/importer.css') }}">
</head>
<body>
  <h1>Bulk Import + Chunked Upload</h1>

  <!-- CSV Import -->
  <div class="card">
    <h2>CSV Import</h2>
    <form id="csvForm" class="row" onsubmit="return false;">
      <label>CSV:
        <input type="file" name="csv" accept=".csv,text/csv" required />
      </label>
      <label>Images dir (under storage/app):
        <input class="inp" type="text" name="images_dir" value="import_images" />
      </label>
      <button id="csvBtn" class="btn" type="button">Import CSV</button>
    </form>

    <div id="csvStats" class="mt-10"></div>
    <div id="csvErrors" class="mt-8"></div>
    <pre id="csvResult" class="mono mt-10"></pre>
  </div>

  <!-- Chunked Upload -->
  <div class="card">
    <h2>Image Upload (chunked, resumable)</h2>
    <div class="row mb-8">
      <label>Attach to Product ID (optional):
        <input id="productId" class="inp" type="number" min="1" placeholder="e.g. 12" />
      </label>
    </div>

    <div class="dropzone" id="dz">Drop image here or click to choose</div>
    <input type="file" id="fileInput" accept="image/*" class="hidden" />

    <div class="row mt-12">
      <progress id="bar" max="100" value="0"></progress>
      <span id="status" class="muted"></span>
    </div>
    <div id="lastLink" class="muted mt-8"></div>
    <pre id="uploadLog" class="mono"></pre>
  </div>

  <script src="{{ asset('js/importer.js') }}" defer></script>
</body>
</html>
