(function () {
  const state = {
    items: [],
    meta: { total: 0, page: 1, per_page: 12, total_pages: 1 },
    options: { movies: [], cinemas: [], cities: [] },
    filters: {
      page: 1,
      per_page: 12,
      search: '',
      movie_id: '',
      cinema_id: '',
      city: '',
      show_date: '',
    },
    searchTimer: null,
    initialized: false,
  };

  const dom = {};

  function initShowtimesPage() {
    if (state.initialized || document.body?.dataset?.activePage !== 'showtimes') {
      return;
    }

    cacheDom();
    if (!dom.grid || !dom.state) {
      return;
    }

    state.initialized = true;
    bindEvents();
    renderStateCard('Loading showtimes...', 'Please wait while we load the latest screenings.');
    loadShowtimes();
  }

  function cacheDom() {
    dom.searchInput = document.getElementById('publicShowtimeSearchInput');
    dom.movieFilter = document.getElementById('publicShowtimeMovieFilter');
    dom.cinemaFilter = document.getElementById('publicShowtimeCinemaFilter');
    dom.cityFilter = document.getElementById('publicShowtimeCityFilter');
    dom.dateFilter = document.getElementById('publicShowtimeDateFilter');
    dom.status = document.getElementById('publicShowtimeStatus');
    dom.state = document.getElementById('publicShowtimeState');
    dom.grid = document.getElementById('publicShowtimesGrid');
    dom.pagination = document.getElementById('publicShowtimesPagination');
  }

  function bindEvents() {
    dom.searchInput?.addEventListener('input', () => {
      window.clearTimeout(state.searchTimer);
      state.searchTimer = window.setTimeout(() => {
        state.filters.search = dom.searchInput.value.trim();
        state.filters.page = 1;
        loadShowtimes();
      }, 250);
    });
    dom.movieFilter?.addEventListener('change', () => {
      state.filters.movie_id = dom.movieFilter.value;
      state.filters.page = 1;
      loadShowtimes();
    });
    dom.cinemaFilter?.addEventListener('change', () => {
      state.filters.cinema_id = dom.cinemaFilter.value;
      state.filters.page = 1;
      loadShowtimes();
    });
    dom.cityFilter?.addEventListener('change', () => {
      state.filters.city = dom.cityFilter.value;
      state.filters.page = 1;
      loadShowtimes();
    });
    dom.dateFilter?.addEventListener('change', () => {
      state.filters.show_date = dom.dateFilter.value;
      state.filters.page = 1;
      loadShowtimes();
    });
    dom.pagination?.addEventListener('click', handlePaginationAction);
    dom.grid?.addEventListener('click', handleCardAction);
  }

  async function loadShowtimes() {
    updateStatus('Loading showtimes...');

    try {
      const response = await fetchJson('/api/showtimes', {
        query: state.filters,
      });
      const payload = response?.data || {};

      state.items = Array.isArray(payload.items) ? payload.items : [];
      state.meta = normalizeMeta(payload.meta);
      state.options = {
        movies: Array.isArray(payload.options?.movies) ? payload.options.movies : [],
        cinemas: Array.isArray(payload.options?.cinemas) ? payload.options.cinemas : [],
        cities: Array.isArray(payload.options?.cities) ? payload.options.cities : [],
      };

      renderFilters();
      renderGrid();
      renderPagination();
      updateStatus(`${state.meta.total} screening${state.meta.total === 1 ? '' : 's'} found`);
    } catch (error) {
      state.items = [];
      state.meta = normalizeMeta();
      renderStateCard('Showtimes are unavailable.', error.message || 'The showtime catalog could not be loaded right now.');
      renderPagination();
      updateStatus('Showtime catalog unavailable');
    }
  }

  function renderFilters() {
    renderSelect(dom.movieFilter, 'All Movies', state.options.movies.map(movie => ({
      value: movie.id,
      label: movie.title,
    })), state.filters.movie_id);

    renderSelect(dom.cinemaFilter, 'All Cinemas', state.options.cinemas.map(cinema => ({
      value: cinema.id,
      label: cinema.city ? `${cinema.name} - ${cinema.city}` : cinema.name,
    })), state.filters.cinema_id);

    renderSelect(dom.cityFilter, 'All Cities', state.options.cities.map(city => ({
      value: city,
      label: city,
    })), state.filters.city);
  }

  function renderGrid() {
    if (!dom.grid || !dom.state) {
      return;
    }

    if (state.items.length === 0) {
      renderStateCard(
        'No showtimes matched your filters.',
        'Try another movie, cinema, city, or date to continue browsing screenings.'
      );
      dom.grid.innerHTML = '';
      return;
    }

    dom.state.innerHTML = '';
    dom.grid.innerHTML = state.items.map(showtime => `
      <article class="card showtime-list-card">
        <div class="showtime-list-poster">
          ${showtime.poster_url
            ? `<img src="${escapeHtmlAttr(showtime.poster_url)}" alt="${escapeHtmlAttr(showtime.movie_title || 'Movie poster')}" loading="lazy">`
            : `<div class="product-img-fallback" style="height:100%;">MV</div>`}
        </div>
        <div class="showtime-list-copy">
          <div class="showtime-list-top">
            <div>
              <h3 class="showtime-list-title">${escapeHtml(showtime.movie_title || 'Untitled movie')}</h3>
              <p class="showtime-list-meta">${escapeHtml([showtime.cinema_name, showtime.room_name, showtime.cinema_city].filter(Boolean).join(' - '))}</p>
            </div>
            <div class="showtime-list-badges">
              <span class="badge blue">${escapeHtml(presentationLabel(showtime.presentation_type))}</span>
              <span class="badge gray">${escapeHtml(languageLabel(showtime.language_version))}</span>
              ${availabilityBadge(showtime.availability_label, showtime.is_sold_out, showtime.status)}
            </div>
          </div>
          <div class="showtime-list-details">
            <div><strong>${escapeHtml(formatDate(showtime.show_date))}</strong></div>
            <div>${escapeHtml(formatTimeRange(showtime.start_time, showtime.end_time))}</div>
            <div>${escapeHtml(formatCurrency(showtime.price))}</div>
            <div>${escapeHtml(`${Number(showtime.available_seats || 0)} seats left`)}</div>
          </div>
          <div class="time-chips">
            <button
              class="time-chip showtime-chip${showtime.is_sold_out || showtime.status !== 'published' ? ' unavailable full' : ''}"
              type="button"
              data-seat-url="${escapeHtmlAttr(String(showtime.seat_selection_url || ''))}"
              ${showtime.is_sold_out || showtime.status !== 'published' ? 'disabled' : ''}
            >
              <span class="showtime-chip-time">${escapeHtml(trimTimeValue(showtime.start_time))}</span>
              <span class="time-chip-note">${escapeHtml(showtime.availability_label || 'Available')}</span>
            </button>
          </div>
        </div>
      </article>
    `).join('');
  }

  function renderStateCard(title, message) {
    if (!dom.state) {
      return;
    }

    dom.state.innerHTML = `
      <div class="detail-state-card detail-state-card-compact" style="margin-bottom:16px;">
        <div>
          <strong>${escapeHtml(title)}</strong>
          <div>${escapeHtml(message)}</div>
        </div>
      </div>
    `;
  }

  function renderPagination() {
    if (!dom.pagination) {
      return;
    }

    const total = Number(state.meta.total || 0);
    const page = Number(state.meta.page || 1);
    const totalPages = Math.max(1, Number(state.meta.total_pages || 1));
    const perPage = Math.max(1, Number(state.meta.per_page || 12));
    const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const end = total === 0 ? 0 : Math.min(total, start + state.items.length - 1);
    const pages = buildVisiblePages(page, totalPages);

    dom.pagination.innerHTML = `
      <div class="pagination">
        <div class="pagination-info">Showing ${start}-${end} of ${total} showtimes</div>
        <div class="pagination-btns">
          <button class="pg-btn" type="button" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>&lt;</button>
          ${pages.map(item => item === 'ellipsis'
            ? '<button class="pg-btn" type="button" disabled>...</button>'
            : `<button class="pg-btn${item === page ? ' active' : ''}" type="button" data-page="${item}">${item}</button>`).join('')}
          <button class="pg-btn" type="button" data-page="${page + 1}" ${page >= totalPages ? 'disabled' : ''}>&gt;</button>
        </div>
      </div>
    `;
  }

  function handlePaginationAction(event) {
    const button = event.target.closest('button[data-page]');
    if (!button || button.disabled) {
      return;
    }

    const targetPage = Number(button.dataset.page || 1);
    if (!Number.isFinite(targetPage) || targetPage < 1 || targetPage === state.filters.page) {
      return;
    }

    state.filters.page = targetPage;
    loadShowtimes();
  }

  function handleCardAction(event) {
    const button = event.target.closest('[data-seat-url]');
    if (!button || button.disabled) {
      return;
    }

    const targetUrl = String(button.dataset.seatUrl || '').trim();
    if (!targetUrl) {
      return;
    }

    window.location.href = appUrl(targetUrl);
  }

  function renderSelect(element, placeholder, items, selectedValue) {
    if (!element) {
      return;
    }

    const options = [`<option value="">${escapeHtml(placeholder)}</option>`];
    items.forEach(item => {
      const selected = String(item.value || '') === String(selectedValue || '') ? ' selected' : '';
      options.push(`<option value="${escapeHtmlAttr(item.value)}"${selected}>${escapeHtml(item.label || '')}</option>`);
    });
    element.innerHTML = options.join('');
  }

  function availabilityBadge(label, isSoldOut, status) {
    const normalizedLabel = String(label || '').trim() || 'Available';
    const tone = status === 'cancelled'
      ? 'red'
      : status === 'draft'
        ? 'gray'
        : isSoldOut
          ? 'red'
          : normalizedLabel.includes('left')
            ? 'orange'
            : 'green';

    return `<span class="badge ${tone}">${escapeHtml(normalizedLabel)}</span>`;
  }

  function normalizeMeta(meta) {
    return {
      total: Number(meta?.total || 0),
      page: Number(meta?.page || 1),
      per_page: Number(meta?.per_page || state.filters.per_page || 12),
      total_pages: Math.max(1, Number(meta?.total_pages || 1)),
    };
  }

  function buildVisiblePages(currentPage, totalPages) {
    if (totalPages <= 5) {
      return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const pages = [1];
    const start = Math.max(2, currentPage - 1);
    const end = Math.min(totalPages - 1, currentPage + 1);
    if (start > 2) pages.push('ellipsis');
    for (let page = start; page <= end; page += 1) pages.push(page);
    if (end < totalPages - 1) pages.push('ellipsis');
    pages.push(totalPages);
    return pages;
  }

  function fetchJson(path, options) {
    const query = buildQueryString(options?.query || {});
    return fetch(appUrl(`${path}${query}`), {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    }).then(async response => {
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(firstErrorMessage(payload?.errors, payload?.message || 'Request failed.'));
      }
      return payload || {};
    });
  }

  function buildQueryString(params) {
    const search = new URLSearchParams();
    Object.entries(params || {}).forEach(([key, value]) => {
      if (value === null || value === undefined || value === '') return;
      search.append(key, String(value));
    });
    const query = search.toString();
    return query ? `?${query}` : '';
  }

  function updateStatus(text) {
    if (dom.status) {
      dom.status.textContent = text;
    }
  }

  function appUrl(path) {
    const basePath = typeof window.APP_BASE_PATH === 'string' ? window.APP_BASE_PATH : '';
    const normalizedPath = String(path || '').startsWith('/') ? path : `/${path}`;
    return `${basePath}${normalizedPath}`;
  }

  function presentationLabel(value) {
    const match = {
      '2d': '2D',
      '3d': '3D',
      imax: 'IMAX',
      '4dx': '4DX',
      screenx: 'ScreenX',
      dolby_atmos: 'Dolby Atmos',
    }[String(value || '').toLowerCase()];

    return match || humanizeStatus(value);
  }

  function languageLabel(value) {
    const match = {
      original: 'Original',
      subtitled: 'Subtitled',
      dubbed: 'Dubbed',
    }[String(value || '').toLowerCase()];

    return match || humanizeStatus(value);
  }

  function trimTimeValue(value) {
    const text = String(value || '').trim();
    return text ? text.slice(0, 5) : 'TBD';
  }

  function formatTimeRange(startTime, endTime) {
    return `${trimTimeValue(startTime)} - ${trimTimeValue(endTime)}`;
  }

  function formatDate(value) {
    if (!value) return 'TBD';
    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(parsed);
  }

  function formatCurrency(value) {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND',
      maximumFractionDigits: 0,
    }).format(amount);
  }

  function firstErrorMessage(errors, fallback) {
    if (!errors || typeof errors !== 'object') return fallback || 'Request failed.';
    for (const messages of Object.values(errors)) {
      if (Array.isArray(messages) && messages.length > 0) return String(messages[0]);
    }
    return fallback || 'Request failed.';
  }

  function humanizeStatus(value) {
    return String(value || '')
      .split('_')
      .filter(Boolean)
      .map(part => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ') || 'Unknown';
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeHtmlAttr(value) {
    return escapeHtml(value).replace(/`/g, '&#96;');
  }

  document.addEventListener('DOMContentLoaded', initShowtimesPage);
})();
