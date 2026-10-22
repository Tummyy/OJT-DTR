/* OJTSync — JavaScript */

// ── Theme toggle & persistence ───────────────────
const themeToggles = Array.from(document.querySelectorAll('.theme-toggle-input-js'));

// ── Password show/hide toggle ────────────────────
function initPasswordToggles() {
  const passwordInputs = document.querySelectorAll('input[type="password"]:not([data-password-toggle-ready])');

  passwordInputs.forEach(input => {
    input.dataset.passwordToggleReady = 'true';

    const wrapper = document.createElement('div');
    wrapper.className = 'password-field';

    const parent = input.parentNode;
    if (!parent) {
      return;
    }

    parent.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'password-toggle-btn';
    toggleBtn.textContent = 'Show';
    toggleBtn.setAttribute('aria-label', 'Show password');
    toggleBtn.setAttribute('aria-pressed', 'false');

    toggleBtn.addEventListener('click', () => {
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      toggleBtn.textContent = isHidden ? 'Hide' : 'Show';
      toggleBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
      toggleBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
    });

    wrapper.appendChild(toggleBtn);
  });
}

initPasswordToggles();

if (themeToggles.length > 0) {
  const htmlRoot = document.documentElement;
  const initialTheme = htmlRoot.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  themeToggles.forEach(toggle => {
    toggle.checked = initialTheme === 'dark';
  });
  htmlRoot.setAttribute('data-theme', initialTheme);

  themeToggles.forEach(toggle => toggle.addEventListener('change', async e => {
    const sourceToggle = e.currentTarget;
    const previousTheme = htmlRoot.getAttribute('data-theme') || 'light';
    const nextTheme = sourceToggle.checked ? 'dark' : 'light';
    htmlRoot.setAttribute('data-theme', nextTheme);
    themeToggles.forEach(t => {
      t.checked = nextTheme === 'dark';
    });

    const payload = new URLSearchParams();
    payload.set('theme', nextTheme);
    payload.set('csrf_token', sourceToggle.dataset.csrf || '');

    try {
      const response = await fetch('update-theme.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: payload.toString()
      });

      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.error || 'Failed to save theme');
      }
    } catch (_err) {
      htmlRoot.setAttribute('data-theme', previousTheme);
      themeToggles.forEach(t => {
        t.checked = previousTheme === 'dark';
      });
      alert('Unable to save theme right now. Please try again.');
    }
  }));
}

// ── Nav toggle (explicit control) ───────────────
const navToggle = document.getElementById('navToggle');
const navCollapse = document.getElementById('mainNavbar');
const navBackdrop = document.getElementById('navBackdrop');
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (navToggle && navCollapse) {
  let navCloseTimer = null;
  navToggle.setAttribute('aria-label', 'Open menu');

  const syncBackdrop = isOpen => {
    if (!navBackdrop) {
      return;
    }
    if (isOpen) {
      navBackdrop.classList.add('show');
      navBackdrop.setAttribute('aria-hidden', 'false');
    } else {
      navBackdrop.classList.remove('show');
      navBackdrop.setAttribute('aria-hidden', 'true');
    }
  };

  const openMobileNav = () => {
    if (navCloseTimer) {
      clearTimeout(navCloseTimer);
      navCloseTimer = null;
    }
    navCollapse.classList.remove('is-closing');
    navCollapse.classList.add('show');
    navToggle.setAttribute('aria-expanded', 'true');
    navToggle.setAttribute('aria-label', 'Close menu');
    navToggle.classList.add('is-open');
    syncBackdrop(true);
    document.body.classList.add('mobile-nav-open');
  };

  const closeMobileNav = () => {
    if (!navCollapse.classList.contains('show')) {
      return;
    }
    if (navCloseTimer) {
      clearTimeout(navCloseTimer);
    }
    navCollapse.classList.add('is-closing');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Open menu');
    navToggle.classList.remove('is-open');
    syncBackdrop(false);
    document.body.classList.remove('mobile-nav-open');
    navCloseTimer = window.setTimeout(() => {
      navCollapse.classList.remove('show', 'is-closing');
      navCloseTimer = null;
    }, 360);
  };

  const resetDesktopNav = () => {
    if (window.innerWidth > 992) {
      navCollapse.classList.remove('show', 'is-closing');
      navToggle.classList.remove('is-open');
      navToggle.setAttribute('aria-expanded', 'false');
      navToggle.setAttribute('aria-label', 'Open menu');
      syncBackdrop(false);
      document.body.classList.remove('mobile-nav-open');
      if (navCloseTimer) {
        clearTimeout(navCloseTimer);
        navCloseTimer = null;
      }
    }
  };

  navToggle.addEventListener('click', () => {
    if (navCollapse.classList.contains('show') && !navCollapse.classList.contains('is-closing')) {
      closeMobileNav();
      return;
    }
    openMobileNav();
  });

  // Auto-close mobile menu when a nav link is clicked.
  navCollapse.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      closeMobileNav();
    });
  });

  if (navBackdrop) {
    navBackdrop.addEventListener('click', closeMobileNav);
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && navCollapse.classList.contains('show')) {
      closeMobileNav();
    }
  });

  window.addEventListener('resize', resetDesktopNav);
}

// ── Animate progress bar ─────────────────────────
document.querySelectorAll('.progress-bar-fill').forEach(bar => {
  const target = bar.getAttribute('data-width') || bar.style.width;
  if (prefersReducedMotion) {
    bar.style.width = target;
    return;
  }
  bar.style.width = '0%';
  setTimeout(() => { bar.style.width = target; }, 200);
});

// ── Modal helpers ────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('active');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('active');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('active');
  });
});

// ── Overtime toggle ───────────────────────────────
const otToggle = document.getElementById('allow_overtime');
const otFields = document.getElementById('overtime-fields');

if (otToggle && otFields) {
  function toggleOT() {
    otFields.style.display = otToggle.checked ? 'block' : 'none';
  }
  toggleOT();
  otToggle.addEventListener('change', toggleOT);
}

// ── Time computation preview ──────────────────────
function computePreview() {
  const tin  = document.getElementById('time_in');
  const tout = document.getElementById('time_out');
  const brk  = document.getElementById('break_duration');
  const maxH = document.getElementById('max_hours');
  const preview = document.getElementById('hours-preview');

  if (!tin || !tout || !preview) return;

  const inVal  = tin.value;
  const outVal = tout.value;
  if (!inVal || !outVal) { preview.style.display = 'none'; return; }

  const brkVal  = parseFloat(brk?.value || 0);
  const maxHVal = parseFloat(maxH?.value || 8);

  // Parse times
  function toH(t) {
    const [h,m] = t.split(':').map(Number);
    return h + m/60;
  }

  let inH  = toH(inVal);
  let outH = toH(outVal);
  if (outH <= inH) outH += 24;

  const raw    = outH - inH;
  const worked = Math.max(0, raw - brkVal);
  const reg    = Math.min(worked, maxHVal);
  const excess = Math.max(0, worked - maxHVal);
  const ot     = excess;
  const total  = worked;

  function fmt(h) {
    if (h <= 0) return '0:00';
    const hh = Math.floor(h);
    const mm = Math.round((h - hh) * 60);
    return hh + ':' + String(mm).padStart(2,'0');
  }

  const rows = [
    ['Raw hours',      fmt(raw)],
    ['Break deducted', fmt(brkVal)],
    ['Worked hours',   fmt(worked)],
    ['Regular hours',  fmt(reg)],
    ['Overtime',       excess > 0 ? fmt(ot) : '—'],
    ['Total hours',    fmt(total)],
  ];

  let html = '<table class="preview-table"><tbody>';
  rows.forEach(([l,v]) => {
    html += `<tr><td>${l}</td><td class="td-val"><strong>${v}</strong></td></tr>`;
  });
  html += '</tbody></table>';

  preview.innerHTML = html;
  preview.style.display = 'block';
}

const timeInputs = ['time_in','time_out','break_duration','max_hours'];
timeInputs.forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('change', computePreview);
});

// Initial preview
computePreview();

// ── Auto-dismiss alerts ───────────────────────────
document.querySelectorAll('.alert').forEach(alert => {
  setTimeout(() => {
    alert.style.transition = 'opacity .4s';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 400);
  }, 5000);
});

// ── Confirm actions (custom modal) ────────────────
const appConfirmModal = document.getElementById('appConfirmModal');
const appConfirmMessage = document.getElementById('appConfirmMessage');
const appConfirmOk = document.getElementById('appConfirmOk');
const appConfirmCancel = document.getElementById('appConfirmCancel');
const appConfirmClose = document.getElementById('appConfirmClose');
const appConfirmTitle = document.getElementById('appConfirmTitle');
const appConfirmIcon = document.getElementById('appConfirmIcon');
const appConfirmNote = document.getElementById('appConfirmNote');

let appConfirmTarget = null;

function closeAppConfirm() {
  if (!appConfirmModal) return;
  appConfirmModal.classList.remove('active');
  appConfirmModal.classList.remove('confirm-modal--danger');
  appConfirmModal.setAttribute('aria-hidden', 'true');
  if (appConfirmNote) {
    appConfirmNote.textContent = '';
    appConfirmNote.hidden = true;
  }
  appConfirmTarget = null;
}

function openAppConfirm(targetEl) {
  if (!appConfirmModal || !targetEl) return;
  const msg = targetEl.getAttribute('data-confirm') || 'Are you sure you want to continue?';
  const okLabel = targetEl.getAttribute('data-confirm-ok') || 'Continue';
  const title = targetEl.getAttribute('data-confirm-title') || 'Confirm Action';
  const icon = targetEl.getAttribute('data-confirm-icon') || '!';
  const note = targetEl.getAttribute('data-confirm-note') || '';
  const variant = targetEl.getAttribute('data-confirm-variant') || 'default';

  appConfirmMessage.textContent = msg;
  appConfirmOk.textContent = okLabel;
  if (appConfirmTitle) appConfirmTitle.textContent = title;
  if (appConfirmIcon) appConfirmIcon.textContent = icon;
  if (appConfirmNote) {
    appConfirmNote.textContent = note;
    appConfirmNote.hidden = note === '';
  }

  appConfirmModal.classList.remove('confirm-modal--danger');
  appConfirmOk.className = 'btn btn-sm';
  if (variant === 'danger') {
    appConfirmModal.classList.add('confirm-modal--danger');
    appConfirmOk.classList.add('btn-danger');
  } else {
    appConfirmOk.classList.add('btn-primary');
  }

  appConfirmTarget = targetEl;

  appConfirmModal.classList.add('active');
  appConfirmModal.setAttribute('aria-hidden', 'false');
  appConfirmOk.focus();
}

if (appConfirmModal && appConfirmMessage && appConfirmOk && appConfirmCancel && appConfirmClose) {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (el.dataset.confirmBypass === 'true') {
        el.dataset.confirmBypass = 'false';
        return;
      }

      e.preventDefault();
      openAppConfirm(el);
    });
  });

  appConfirmOk.addEventListener('click', () => {
    if (!appConfirmTarget) {
      closeAppConfirm();
      return;
    }

    const target = appConfirmTarget;
    closeAppConfirm();

    target.dataset.confirmBypass = 'true';
    target.click();
  });

  appConfirmCancel.addEventListener('click', closeAppConfirm);
  appConfirmClose.addEventListener('click', closeAppConfirm);

  appConfirmModal.addEventListener('click', e => {
    if (e.target === appConfirmModal) {
      closeAppConfirm();
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && appConfirmModal.classList.contains('active')) {
      closeAppConfirm();
    }
  });
} else {
  // Fallback to browser confirm if modal markup is unavailable.
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });
}

// ── Rest days checkbox ────────────────────────────
// Handled inline in schedule.php via toggleRestLabel().

// ── Print report ─────────────────────────────────
window.printReport = function() {
  window.print();
};

// ── Dashboard KPI tooltips (single-open) ────────
const kpiTooltips = Array.from(document.querySelectorAll('.kpi-help'));

if (kpiTooltips.length > 0) {
  kpiTooltips.forEach(tooltip => {
    tooltip.addEventListener('toggle', () => {
      if (!tooltip.open) {
        return;
      }

      kpiTooltips.forEach(other => {
        if (other !== tooltip) {
          other.removeAttribute('open');
        }
      });
    });
  });

  document.addEventListener('click', e => {
    if (e.target.closest('.kpi-help')) {
      return;
    }

    kpiTooltips.forEach(tooltip => tooltip.removeAttribute('open'));
  });
}

// ── Profile delete modal ─────────────────────────
const deleteModal = document.getElementById('deleteAccountModal');
const deleteModalOpener = document.querySelector('[data-delete-modal-open="true"]');

function closeDeleteModal() {
  if (!deleteModal) {
    return;
  }
  deleteModal.classList.remove('active');
  deleteModal.setAttribute('aria-hidden', 'true');
}

function openDeleteModal() {
  if (!deleteModal) {
    return;
  }
  deleteModal.classList.add('active');
  deleteModal.setAttribute('aria-hidden', 'false');

  const firstField = deleteModal.querySelector('input,button,select,textarea');
  if (firstField) {
    firstField.focus();
  }
}

if (deleteModal && deleteModalOpener) {
  deleteModalOpener.addEventListener('click', openDeleteModal);

  deleteModal.querySelectorAll('[data-delete-modal-close="true"]').forEach(btn => {
    btn.addEventListener('click', closeDeleteModal);
  });

  deleteModal.addEventListener('click', e => {
    if (e.target === deleteModal) {
      closeDeleteModal();
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && deleteModal.classList.contains('active')) {
      closeDeleteModal();
    }
  });
}

// ── Landing reveal-on-scroll ───────────────────
const revealItems = Array.from(document.querySelectorAll('.reveal-on-scroll'));

if (revealItems.length > 0) {
  if (prefersReducedMotion) {
    revealItems.forEach(item => item.classList.add('is-visible'));
  } else if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      });
    }, {
      threshold: 0.12,
      rootMargin: '0px 0px -8% 0px'
    });

    revealItems.forEach(item => revealObserver.observe(item));
  } else {
    revealItems.forEach(item => item.classList.add('is-visible'));
  }
}

// ── Landing demo computation (V1.3) ───────────
const demoTimeIn = document.getElementById('demoTimeIn');
const demoTimeOut = document.getElementById('demoTimeOut');
const demoBreak = document.getElementById('demoBreak');
const demoRegularCap = document.getElementById('demoRegularCap');
const demoRequiredHours = document.getElementById('demoRequiredHours');
const demoRenderedHours = document.getElementById('demoRenderedHours');
const demoWorkdays = document.getElementById('demoWorkdays');
const demoTargetWeeks = document.getElementById('demoTargetWeeks');
const demoWorked = document.getElementById('demoWorked');
const demoRegular = document.getElementById('demoRegular');
const demoOvertime = document.getElementById('demoOvertime');
const demoRemaining = document.getElementById('demoRemaining');
const demoFinishDate = document.getElementById('demoFinishDate');
const demoWeeksLeft = document.getElementById('demoWeeksLeft');
const demoPaceBadge = document.getElementById('demoPaceBadge');

if (
  demoTimeIn && demoTimeOut && demoBreak && demoRegularCap &&
  demoRequiredHours && demoRenderedHours && demoWorkdays && demoTargetWeeks &&
  demoWorked && demoRegular && demoOvertime && demoRemaining &&
  demoFinishDate && demoWeeksLeft && demoPaceBadge
) {
  const toHours = timeValue => {
    const [hh, mm] = timeValue.split(':').map(Number);
    return hh + (mm / 60);
  };

  const toHM = value => {
    const safe = Math.max(0, value);
    const h = Math.floor(safe);
    const m = Math.round((safe - h) * 60);

    if (m === 60) {
      return `${h + 1}:00`;
    }

    return `${h}:${String(m).padStart(2, '0')}`;
  };

  const updateLandingDemo = () => {
    const inVal = demoTimeIn.value;
    const outVal = demoTimeOut.value;
    const breakVal = parseFloat(demoBreak.value || '0');
    const capVal = parseFloat(demoRegularCap.value || '8');
    const requiredVal = parseFloat(demoRequiredHours.value || '0');
    const renderedVal = parseFloat(demoRenderedHours.value || '0');
    const workdaysVal = parseFloat(demoWorkdays.value || '5');
    const targetWeeksVal = parseFloat(demoTargetWeeks.value || '16');

    if (
      !inVal || !outVal ||
      Number.isNaN(breakVal) || Number.isNaN(capVal) ||
      Number.isNaN(requiredVal) || Number.isNaN(renderedVal) ||
      Number.isNaN(workdaysVal) || Number.isNaN(targetWeeksVal)
    ) {
      return;
    }

    let start = toHours(inVal);
    let end = toHours(outVal);

    if (end <= start) {
      end += 24;
    }

    const raw = end - start;
    const worked = Math.max(0, raw - Math.max(0, breakVal));
    const regular = Math.min(worked, Math.max(0, capVal));
    const overtime = Math.max(0, worked - regular);
    const remaining = Math.max(0, Math.max(0, requiredVal) - Math.max(0, renderedVal));

    demoWorked.textContent = toHM(worked);
    demoRegular.textContent = toHM(regular);
    demoOvertime.textContent = toHM(overtime);
    demoRemaining.textContent = toHM(remaining);

    const dailyProjected = worked;
    const weekDays = Math.max(1, Math.min(7, Math.round(workdaysVal)));

    if (remaining <= 0) {
      demoFinishDate.textContent = 'Completed';
      demoWeeksLeft.textContent = 'You have already met the required hours.';
      demoPaceBadge.textContent = 'Ahead';
      demoPaceBadge.className = 'demo-pace-badge ahead';
      return;
    }

    if (dailyProjected <= 0) {
      demoFinishDate.textContent = '--';
      demoWeeksLeft.textContent = 'Add a working schedule above to estimate completion.';
      demoPaceBadge.textContent = 'Not enough data';
      demoPaceBadge.className = 'demo-pace-badge neutral';
      return;
    }

    const workdaysNeeded = remaining / dailyProjected;
    const weeksNeeded = workdaysNeeded / weekDays;
    const calendarDays = Math.ceil((workdaysNeeded / weekDays) * 7);

    const finishDate = new Date();
    finishDate.setHours(0, 0, 0, 0);
    finishDate.setDate(finishDate.getDate() + calendarDays);

    demoFinishDate.textContent = finishDate.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });

    demoWeeksLeft.textContent = `${weeksNeeded.toFixed(1)} week(s) remaining at this pace.`;

    const safeTarget = Math.max(1, targetWeeksVal);
    if (weeksNeeded <= safeTarget * 0.9) {
      demoPaceBadge.textContent = 'Ahead';
      demoPaceBadge.className = 'demo-pace-badge ahead';
    } else if (weeksNeeded <= safeTarget) {
      demoPaceBadge.textContent = 'On Track';
      demoPaceBadge.className = 'demo-pace-badge track';
    } else {
      demoPaceBadge.textContent = 'Behind';
      demoPaceBadge.className = 'demo-pace-badge behind';
    }
  };

  ['input', 'change'].forEach(evt => {
    demoTimeIn.addEventListener(evt, updateLandingDemo);
    demoTimeOut.addEventListener(evt, updateLandingDemo);
    demoBreak.addEventListener(evt, updateLandingDemo);
    demoRegularCap.addEventListener(evt, updateLandingDemo);
    demoRequiredHours.addEventListener(evt, updateLandingDemo);
    demoRenderedHours.addEventListener(evt, updateLandingDemo);
    demoWorkdays.addEventListener(evt, updateLandingDemo);
    demoTargetWeeks.addEventListener(evt, updateLandingDemo);
  });

  updateLandingDemo();
}
