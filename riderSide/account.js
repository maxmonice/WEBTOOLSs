// =====================================================
// ACCOUNT PAGE JAVASCRIPT
// =====================================================

function toggleSwitch(el) {
  el.classList.toggle('on');
}

function confirmLogout() { doLogout(); }

async function doLogout() {
  try {
    await fetch('rider-auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ action: 'logout' })
    });
  } catch (e) {}
  window.location.href = 'login.php';
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

// ── Load rider rating dynamically ──
function loadRiderRating() {
  // Get rider ID from session (set in account.php)
  const riderIdEl = document.querySelector('.account-id');
  if (!riderIdEl) return;

  // Extract rider ID number from the display string "Rider ID: LKS-R-XXXX"
  const match = riderIdEl.textContent.match(/(\d+)/);
  if (!match) return;
  const riderId = parseInt(match[0]);

  fetch(`../ratings-api.php?action=rider-rating&rider_id=${riderId}`)
    .then(r => r.json())
    .then(data => {
      if (data.success && data.average_rating !== null) {
        const avg = parseFloat(data.average_rating);
        const count = data.rating_count || 0;
        const filled = Math.round(avg);

        // Update stars
        const starsEl = document.getElementById('riderStars');
        if (starsEl) {
          starsEl.textContent = '★'.repeat(filled) + '☆'.repeat(5 - filled);
        }

        // Update rating value
        const valEl = document.getElementById('riderRatingVal');
        if (valEl) {
          valEl.textContent = avg.toFixed(1);
        }
      }
    })
    .catch(() => { /* silently fail, PHP already rendered initial values */ });
}

// Load rating on page ready
document.addEventListener('DOMContentLoaded', loadRiderRating);

function updateClock() {
  const now = new Date();
  const timeEl = document.querySelector('.time');
  if (timeEl) {
    timeEl.textContent =
      now.getHours().toString().padStart(2, '0') + ':' +
      now.getMinutes().toString().padStart(2, '0');
  }
}
updateClock();
setInterval(updateClock, 60000);