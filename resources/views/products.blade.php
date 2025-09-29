<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Products</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="{{ asset('css/importer.css') }}">
</head>
<body>
  <h1>Products</h1>

  <div class="card">
    <h2>Browse & Attach</h2>

    <form id="prodSearch" class="row" onsubmit="return false;">
      <label>Search:
        <input id="q" class="inp" type="text" placeholder="SKU or name">
      </label>
      <label class="row">
        <input id="onlyNoImage" type="checkbox"> Only without image
      </label>
      <label>Per page:
        <input id="perPage" class="inp" type="number" min="1" max="50" value="10">
      </label>
      <button id="prodSearchBtn" class="btn" type="button">Load</button>
      <a class="btn" href="/import-uploader">Go to Importer</a>
    </form>

    <div class="mt-10">
      <table class="table">
        <thead>
          <tr>
            <th class="th">ID</th>
            <th class="th">SKU</th>
            <th class="th">Name</th>
            <th class="th">Price</th>
            <th class="th">Image?</th>
            <th class="th">Action</th>
          </tr>
        </thead>
        <tbody id="prodRows"></tbody>
      </table>
    </div>

    <div class="row mt-12">
      <button id="prevPage" class="btn" type="button">Prev</button>
      <span id="pageInfo" class="muted"></span>
      <button id="nextPage" class="btn" type="button">Next</button>
    </div>
  </div>

  <script src="{{ asset('js/products.js') }}" defer></script>
</body>
</html>
