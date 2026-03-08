document.addEventListener('DOMContentLoaded', () => {

  // ============================================================
  //  THEME TOGGLE
  // ============================================================
  const toggleBtn = document.getElementById('theme-toggle');
  const body = document.body;

  if (toggleBtn) {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
      body.classList.add('light-mode');
      toggleBtn.textContent = '🌙 Dark';
    } else {
      toggleBtn.textContent = '☀️ Light';
    }

    toggleBtn.addEventListener('click', () => {
      const isLight = body.classList.toggle('light-mode');
      toggleBtn.textContent = isLight ? '🌙 Dark' : '☀️ Light';
      localStorage.setItem('theme', isLight ? 'light' : 'dark');
    });
  }

  // ============================================================
  //  API FETCH WITH RETRY & RATE-LIMIT HANDLING
  // ============================================================
  async function fetchWithRetry(url, options = {}, retries = 3, delay = 1000) {
    for (let attempt = 1; attempt <= retries; attempt++) {
      try {
        const response = await fetch(url, options);

        // Handle Binance rate limit (HTTP 429)
        if (response.status === 429) {
          const retryAfter = parseInt(response.headers.get('Retry-After') || '5', 10);
          console.warn(`Rate limited. Retrying after ${retryAfter}s (attempt ${attempt}/${retries})`);
          await new Promise(r => setTimeout(r, retryAfter * 1000));
          continue;
        }

        // Handle server errors (5xx)
        if (response.status >= 500 && attempt < retries) {
          console.warn(`Server error ${response.status}. Retrying... (attempt ${attempt}/${retries})`);
          await new Promise(r => setTimeout(r, delay * attempt));
          continue;
        }

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
      } catch (err) {
        if (attempt === retries) {
          console.error(`Failed after ${retries} attempts:`, err.message);
          return null;
        }
        console.warn(`Fetch error, retrying... (attempt ${attempt}/${retries}):`, err.message);
        await new Promise(r => setTimeout(r, delay * attempt));
      }
    }
    return null;
  }

  // ============================================================
  //  BALANCE FETCHING
  // ============================================================
  async function fetchBalance() {
    const data = await fetchWithRetry('get_balance.php');
    if (data && 'balance' in data) {
      document.getElementById('usdt-amount').textContent =
        parseFloat(data.balance).toFixed(2) + ' USDT';
      document.getElementById('btc-amount').textContent =
        parseFloat(data.btc_balance).toFixed(6) + ' BTC';
      const eth = data.eth_balance;
      const parsed = parseFloat(eth);
      document.getElementById('eth-amount').textContent =
        !isNaN(parsed) ? parsed.toFixed(6) + ' ETH' : (eth || 'Unavailable ETH');
    }
  }

  // ============================================================
  //  RISK METRICS PANEL
  // ============================================================
  async function fetchRiskMetrics() {
    const data = await fetchWithRetry('get_risk_metrics.php');
    if (!data || data.error) return;

    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };

    set('risk-portfolio-value', '$' + data.portfolio_value.toLocaleString());
    set('risk-btc-exposure', data.btc_exposure_percent + '%');
    set('risk-daily-volume', '$' + data.daily_volume_used.toLocaleString() + ' / $' + data.daily_volume_limit.toLocaleString());
    set('risk-daily-trades', data.daily_trades + ' / ' + data.daily_trades_limit);
    set('risk-drawdown', data.drawdown_percent + '%');

    const levelEl = document.getElementById('risk-level');
    if (levelEl) {
      levelEl.textContent = data.risk_level;
      levelEl.style.color =
        data.risk_level === 'HIGH' ? '#e74c3c' :
        data.risk_level === 'MEDIUM' ? '#f39c12' : '#2ecc71';
    }
  }

  // ============================================================
  //  PRICE UPDATES (24h ticker) — via server-side proxy
  // ============================================================
  async function updatePrices() {
    const data = await fetchWithRetry('api.php');
    if (!data) return;

    const btcData = data['BTCUSDT'];
    const ethData = data['ETHUSDT'];
    if (btcData?.error || ethData?.error) return;

    if (btcData && ethData) {
      document.getElementById('btc-price').textContent = `$${parseFloat(btcData.lastPrice).toFixed(2)}`;
      document.getElementById('eth-price').textContent = `$${parseFloat(ethData.lastPrice).toFixed(2)}`;
      document.getElementById('market-data').innerHTML = `
        <tr>
          <td>BTC</td>
          <td>$${parseFloat(btcData.lastPrice).toFixed(2)}</td>
          <td>$${parseFloat(btcData.highPrice).toFixed(2)}</td>
          <td>$${parseFloat(btcData.lowPrice).toFixed(2)}</td>
          <td>${parseFloat(btcData.volume).toFixed(2)} BTC</td>
        </tr>
        <tr>
          <td>ETH</td>
          <td>$${parseFloat(ethData.lastPrice).toFixed(2)}</td>
          <td>$${parseFloat(ethData.highPrice).toFixed(2)}</td>
          <td>$${parseFloat(ethData.lowPrice).toFixed(2)}</td>
          <td>${parseFloat(ethData.volume).toFixed(2)} ETH</td>
        </tr>`;
    }
  }

  // ============================================================
  //  TECHNICAL INDICATOR CHARTS (RSI, MACD, SMA overlays)
  // ============================================================
  let priceChart = null;
  let rsiChart   = null;
  let macdChart  = null;
  let activeSymbol = null;

  document.getElementById('btc-link').addEventListener('click', e => {
    e.preventDefault();
    showIndicatorChart('BTCUSDT');
  });

  document.getElementById('eth-link').addEventListener('click', e => {
    e.preventDefault();
    showIndicatorChart('ETHUSDT');
  });

  // Re-render when interval or indicator checkboxes change
  const intervalSelect = document.getElementById('chart-interval');
  if (intervalSelect) {
    intervalSelect.addEventListener('change', () => {
      if (activeSymbol) showIndicatorChart(activeSymbol);
    });
  }
  document.getElementById('show-sma')?.addEventListener('change', () => { if (activeSymbol) showIndicatorChart(activeSymbol); });
  document.getElementById('show-rsi')?.addEventListener('change', () => { if (activeSymbol) showIndicatorChart(activeSymbol); });
  document.getElementById('show-macd')?.addEventListener('change', () => { if (activeSymbol) showIndicatorChart(activeSymbol); });

  async function showIndicatorChart(symbol) {
    activeSymbol = symbol;
    const interval = document.getElementById('chart-interval')?.value || '1h';
    const showSMA  = document.getElementById('show-sma')?.checked ?? true;
    const showRSI  = document.getElementById('show-rsi')?.checked ?? true;
    const showMACD = document.getElementById('show-macd')?.checked ?? true;

    const isBTC = symbol === 'BTCUSDT';

    // Show/hide chart containers
    document.getElementById('btc-chart').style.display = isBTC ? 'block' : 'none';
    document.getElementById('eth-chart').style.display = isBTC ? 'none' : 'block';
    document.getElementById('rsi-chart-container').style.display = showRSI ? 'block' : 'none';
    document.getElementById('macd-chart-container').style.display = showMACD ? 'block' : 'none';

    // Fetch indicator data from server
    const data = await fetchWithRetry(`get_indicators.php?symbol=${symbol}&interval=${interval}`);
    if (!data || data.error) {
      console.error('Indicator fetch failed:', data?.error);
      return;
    }

    // --- Price chart with SMA overlays ---
    const canvasId = isBTC ? 'btcChart' : 'ethChart';
    const ctx = document.getElementById(canvasId).getContext('2d');

    if (priceChart) priceChart.destroy();

    const datasets = [{
      label: `${symbol.replace('USDT', '')}/USDT Close`,
      data: data.ohlcv.close,
      borderColor: 'rgba(240,185,11,1)',
      backgroundColor: 'rgba(240,185,11,0.1)',
      fill: true,
      tension: 0.1,
      pointRadius: 0,
      borderWidth: 2
    }];

    if (showSMA) {
      datasets.push({
        label: 'SMA(20)',
        data: data.indicators.sma20,
        borderColor: '#3498db',
        borderWidth: 1.5,
        pointRadius: 0,
        fill: false,
        tension: 0.2
      });
      datasets.push({
        label: 'SMA(50)',
        data: data.indicators.sma50,
        borderColor: '#e74c3c',
        borderWidth: 1.5,
        pointRadius: 0,
        fill: false,
        tension: 0.2
      });
    }

    priceChart = new Chart(ctx, {
      type: 'line',
      data: { labels: data.labels, datasets },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { labels: { color: '#eaeaea' } } },
        scales: {
          x: { ticks: { color: '#eaeaea', maxTicksLimit: 20 } },
          y: { ticks: { color: '#eaeaea' } }
        }
      }
    });

    // --- RSI chart ---
    if (showRSI) {
      const rsiCtx = document.getElementById('rsiChart').getContext('2d');
      if (rsiChart) rsiChart.destroy();

      rsiChart = new Chart(rsiCtx, {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [{
            label: 'RSI (14)',
            data: data.indicators.rsi,
            borderColor: '#9b59b6',
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false,
            tension: 0.2
          }]
        },
        options: {
          responsive: true,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { labels: { color: '#eaeaea' } },
            annotation: {
              annotations: {
                overbought: {
                  type: 'line', yMin: 70, yMax: 70,
                  borderColor: '#e74c3c', borderWidth: 1, borderDash: [5, 5],
                  label: { display: true, content: 'Overbought (70)', position: 'end', color: '#e74c3c', font: { size: 10 } }
                },
                oversold: {
                  type: 'line', yMin: 30, yMax: 30,
                  borderColor: '#2ecc71', borderWidth: 1, borderDash: [5, 5],
                  label: { display: true, content: 'Oversold (30)', position: 'end', color: '#2ecc71', font: { size: 10 } }
                }
              }
            }
          },
          scales: {
            x: { ticks: { color: '#eaeaea', maxTicksLimit: 20 } },
            y: { min: 0, max: 100, ticks: { color: '#eaeaea' } }
          }
        }
      });
    }

    // --- MACD chart ---
    if (showMACD) {
      const macdCtx = document.getElementById('macdChart').getContext('2d');
      if (macdChart) macdChart.destroy();

      // Build histogram colors (green when positive, red when negative)
      const histColors = data.indicators.histogram.map(v =>
        v === null ? 'transparent' : (v >= 0 ? 'rgba(46,204,113,0.6)' : 'rgba(231,76,60,0.6)')
      );

      macdChart = new Chart(macdCtx, {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [
            {
              label: 'MACD Histogram',
              data: data.indicators.histogram,
              backgroundColor: histColors,
              borderWidth: 0,
              type: 'bar',
              order: 2
            },
            {
              label: 'MACD Line',
              data: data.indicators.macd_line,
              borderColor: '#3498db',
              borderWidth: 1.5,
              pointRadius: 0,
              fill: false,
              type: 'line',
              order: 1
            },
            {
              label: 'Signal Line',
              data: data.indicators.signal_line,
              borderColor: '#e67e22',
              borderWidth: 1.5,
              pointRadius: 0,
              fill: false,
              type: 'line',
              order: 1
            }
          ]
        },
        options: {
          responsive: true,
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: { labels: { color: '#eaeaea' } } },
          scales: {
            x: { ticks: { color: '#eaeaea', maxTicksLimit: 20 } },
            y: { ticks: { color: '#eaeaea' } }
          }
        }
      });
    }
  }

  // ============================================================
  //  STRATEGY MONITOR POLLING
  // ============================================================
  setInterval(() => {
    fetchWithRetry('StrategyController.php')
      .then(data => {
        if (!data || data.status === 'STRATEGY_DISABLED') {
          document.getElementById('strategy-ui-status').innerText = 'INACTIVE';
          document.getElementById('strategy-signal').innerText = '-';
          document.getElementById('strategy-sma50').innerText = '-';
          document.getElementById('strategy-sma200').innerText = '-';
          document.getElementById('strategy-action').innerText = '-';
          return;
        }

        document.getElementById('strategy-ui-status').innerText = 'ACTIVE';
        document.getElementById('strategy-signal').innerText = data.signal ?? '-';
        document.getElementById('strategy-sma200').innerText = data.sma200 !== null ? data.sma200.toFixed(2) : '-';
        document.getElementById('strategy-sma50').innerText = data.sma50 !== null ? data.sma50.toFixed(2) : '-';
        document.getElementById('strategy-action').innerText = data.execution ?? '-';
      })
      .catch(() => {});
  }, 5000);

  // ============================================================
  //  INITIAL LOAD & PERIODIC REFRESH
  // ============================================================
  updatePrices();
  setInterval(updatePrices, 5000);
  fetchBalance();
  setInterval(fetchBalance, 15000);
  fetchRiskMetrics();
  setInterval(fetchRiskMetrics, 10000);
});
