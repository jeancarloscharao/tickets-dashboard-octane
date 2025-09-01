<!-- resources/views/dashboard.blade.php -->
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard • Octane</title>

  <!-- Tailwind (CDN para dev; em produção prefira Vite) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Chart.js (para gráficos) -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <style>
    /* fallback escuro leve */
    :root { color-scheme: light dark; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900">
  <div class="max-w-7xl mx-auto p-6 space-y-6">
    <header class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Dashboard (Octane)</h1>
        <p id="meta" class="text-sm text-slate-500">Carregando…</p>
      </div>
      <div class="flex items-center gap-3">
        <label class="text-sm text-slate-600">Auto-atualizar</label>
        <select id="refreshSelect" class="border rounded-md px-2 py-1 text-sm">
          <option value="0">Desligado</option>
          <option value="5">5s</option>
          <option value="15" selected>15s</option>
          <option value="30">30s</option>
          <option value="60">60s</option>
        </select>
        <button id="refreshBtn" class="px-3 py-1.5 rounded-md bg-slate-900 text-white text-sm hover:bg-slate-800">Atualizar agora</button>
      </div>
    </header>

    <!-- KPIs principais -->
    <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <article class="p-5 rounded-2xl bg-white shadow-sm border">
        <div class="text-slate-500 text-xs">Total</div>
        <div id="kpiTotal" class="text-3xl font-semibold">—</div>
      </article>
      <article class="p-5 rounded-2xl bg-white shadow-sm border">
        <div class="text-slate-500 text-xs">Abertos</div>
        <div id="kpiAbertos" class="text-3xl font-semibold">—</div>
      </article>
      <article class="p-5 rounded-2xl bg-white shadow-sm border">
        <div class="text-slate-500 text-xs">Concluídos</div>
        <div id="kpiConcluidos" class="text-3xl font-semibold">—</div>
      </article>
    </section>

    <!-- Gráficos -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="p-5 rounded-2xl bg-white shadow-sm border">
        <h2 class="text-sm font-medium text-slate-700 mb-3">Por status (abertos)</h2>
        <canvas id="statusChart" height="200"></canvas>
      </div>
      <div class="p-5 rounded-2xl bg-white shadow-sm border">
        <h2 class="text-sm font-medium text-slate-700 mb-3">SLA médio (min) — 7d vs 30d</h2>
        <canvas id="slaChart" height="200"></canvas>
      </div>
      <div class="p-5 rounded-2xl bg-white shadow-sm border">
        <h2 class="text-sm font-medium text-slate-700 mb-1">USD/BRL</h2>
        <div class="text-3xl font-semibold" id="usd">—</div>
        <p class="text-xs text-slate-500">Fonte: awesomeapi • atualiza com o dashboard</p>
      </div>
    </section>

    <!-- Últimos 10 -->
    <section class="p-5 rounded-2xl bg-white shadow-sm border">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-medium text-slate-700">Últimos 10 tickets</h2>
        <input id="search" type="search" placeholder="Filtrar por título/status…" class="border rounded-md px-3 py-1 text-sm" />
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-left text-slate-500">
            <tr>
              <th class="py-2 pr-4">ID</th>
              <th class="py-2 pr-4">Título</th>
              <th class="py-2 pr-4">Status</th>
              <th class="py-2 pr-4">Criado em</th>
            </tr>
          </thead>
          <tbody id="last10" class="divide-y">
            <tr><td class="py-3 text-slate-400" colspan="4">Carregando…</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <footer class="text-xs text-slate-500">
      <div>Server-Timing: <code id="timing">—</code></div>
      <div>Request ID: <code id="rid">—</code></div>
      <div>Gerado em: <code id="gen">—</code></div>
    </footer>
  </div>

<script>
const endpoint = '/dashboard-pro'; // backend que você já tem
let statusChart, slaChart;
let timer = null;

function $(sel){ return document.querySelector(sel); }
function fmt(n){ return new Intl.NumberFormat('pt-BR').format(n); }
function fmtDate(s){ return new Date(s.replace(' ', 'T') + 'Z').toLocaleString(); }

async function load(){
  const res = await fetch(endpoint, { cache: 'no-store' });
  const data = await res.json();
  const serverTiming = res.headers.get('Server-Timing');
  const rid = res.headers.get('X-Request-Id');

  // Meta e KPIs
  $('#meta').textContent = serverTiming ? `Server‑Timing: ${serverTiming}` : '—';
  $('#timing').textContent = serverTiming || '—';
  $('#rid').textContent = rid || '—';
  $('#gen').textContent = data.generated_at || '—';

  $('#kpiTotal').textContent = fmt(data.counts?.total ?? 0);
  $('#kpiAbertos').textContent = fmt(data.counts?.abertos ?? 0);
  $('#kpiConcluidos').textContent = fmt(data.counts?.concluidos ?? 0);
  $('#usd').textContent = data.usd_brl ? Number(data.usd_brl).toFixed(4) : '—';

  // Tabela últimos 10 (com filtro simples)
  const tbody = $('#last10');
  const rows = (data.last10 || []).map(r => {
    const badge = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-slate-100">${r.status}</span>`;
    return `<tr>
      <td class="py-2 pr-4 font-mono">${r.id}</td>
      <td class="py-2 pr-4">${escapeHtml(r.title)}</td>
      <td class="py-2 pr-4">${badge}</td>
      <td class="py-2 pr-4 text-slate-600">${fmtDate(r.created_at)}</td>
    </tr>`
  }).join('');
  tbody.innerHTML = rows || '<tr><td class="py-3 text-slate-400" colspan="4">Sem dados</td></tr>';

  // Filtro
  $('#search').oninput = (e) => {
    const q = e.target.value.toLowerCase();
    for (const tr of tbody.querySelectorAll('tr')){
      const text = tr.textContent.toLowerCase();
      tr.style.display = text.includes(q) ? '' : 'none';
    }
  };

  // Gráfico por status
  const labels = Object.keys(data.counts_by_status || {});
  const values = Object.values(data.counts_by_status || {});
  const ctx1 = document.getElementById('statusChart').getContext('2d');
  statusChart?.destroy();
  statusChart = new Chart(ctx1, {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Abertos', data: values }] },
    options: { responsive: true, plugins: { legend: { display: false } } }
  });

  // Gráfico SLA 7d vs 30d
  const sla7r  = data.sla?.['7d']?.avg_response_min ?? 0;
  const sla7c  = data.sla?.['7d']?.avg_resolution_min ?? 0;
  const sla30r = data.sla?.['30d']?.avg_response_min ?? 0;
  const sla30c = data.sla?.['30d']?.avg_resolution_min ?? 0;
  const ctx2 = document.getElementById('slaChart').getContext('2d');
  slaChart?.destroy();
  slaChart = new Chart(ctx2, {
    type: 'bar',
    data: {
      labels: ['Resp. 7d','Conclusão 7d','Resp. 30d','Conclusão 30d'],
      datasets: [{ label: 'min', data: [sla7r, sla7c, sla30r, sla30c] }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
  });
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, c => ({
    '&':'&amp;',
    '<':'&lt;',
    '>':'&gt;',
    '"':'&quot;',
    "'":"&#039;"
  }[c]));
}

// Auto refresh
function setAutoRefresh(sec){
  if (timer) clearInterval(timer);
  if (sec > 0) timer = setInterval(load, sec*1000);
}

$('#refreshBtn').addEventListener('click', load);
$('#refreshSelect').addEventListener('change', (e)=> setAutoRefresh(parseInt(e.target.value,10)));

// start
load();
setAutoRefresh(parseInt($('#refreshSelect').value,10));
</script>
</body>
</html>
