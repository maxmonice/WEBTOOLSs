(function () {
  'use strict';

  function getAdminBookingsPageConfig() {
    var el = document.getElementById('adminBookingsPageConfig');
    if (!el) {
      var n = new Date();
      return { calendarMonth: n.getMonth() + 1, calendarYear: n.getFullYear() };
    }
    try {
      var j = JSON.parse(el.textContent);
      var m = parseInt(j.calendarMonth, 10);
      var y = parseInt(j.calendarYear, 10);
      return {
        calendarMonth: Math.min(12, Math.max(1, isNaN(m) ? new Date().getMonth() + 1 : m)),
        calendarYear: isNaN(y) ? new Date().getFullYear() : y,
      };
    } catch (e) {
      var d = new Date();
      return { calendarMonth: d.getMonth() + 1, calendarYear: d.getFullYear() };
    }
  }

  function parseJsonFromResponse(text) {
    try {
      return JSON.parse(text);
    } catch (e) {
      return null;
    }
  }

  window.toggleSidebar = function () {
    var s = document.getElementById('sidebar');
    if (s) s.classList.toggle('open');
  };

  window.openModal = function (id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('open');
  };

  window.closeModal = function (id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('open');
  };

  window.showToast = function (msg, type) {
    type = type || '';
    var c = document.getElementById('toastContainer');
    if (!c) return;
    var t = document.createElement('div');
    t.className = 'toast' + (type ? ' ' + type : '');
    var icon = type === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle';
    t.innerHTML = '<i class="fa-solid ' + icon + '"></i> ';
    var span = document.createElement('span');
    span.textContent = msg;
    t.appendChild(span);
    c.appendChild(t);
    setTimeout(function () {
      t.remove();
    }, 3800);
  };

  function formatBookingCalendarDate(dateStr) {
    if (!dateStr) return 'N/A';
    var m = String(dateStr).trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return String(dateStr);
    var y = parseInt(m[1], 10);
    var mo = parseInt(m[2], 10);
    var d = parseInt(m[3], 10);
    return new Date(y, mo - 1, d).toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  }

  function sqlOrLabelTimeToDisplay(t) {
    if (t == null || t === '') return 'N/A';
    var s = String(t).trim();
    if (/\s[–—-]\s/.test(s) && /(AM|PM)/i.test(s)) return s;
    var match = s.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?/);
    if (!match) return s;
    var h = parseInt(match[1], 10);
    var min = match[2];
    var sec = match[3];
    var ampm = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12 || 12;
    if (sec && sec !== '00') return h12 + ':' + min + ':' + sec + ' ' + ampm;
    return h12 + ':' + min + ' ' + ampm;
  }

  function formatBookingTimeDisplay(timeStr, optionalEnd) {
    var endRaw = optionalEnd !== undefined && optionalEnd !== null ? String(optionalEnd).trim() : '';
    if (endRaw !== '' && endRaw !== '00:00:00') {
      var a = sqlOrLabelTimeToDisplay(timeStr);
      var b = sqlOrLabelTimeToDisplay(optionalEnd);
      if (a !== 'N/A' && b !== 'N/A' && a !== b) return a + ' – ' + b;
    }
    return sqlOrLabelTimeToDisplay(timeStr);
  }

  function escapeHtml(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function normalizeTimeForInput(t) {
    if (!t) return '';
    var s = String(t).trim();
    var m = s.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?/);
    if (!m) return s.length >= 5 ? s.slice(0, 5) : s;
    var hh = ('0' + parseInt(m[1], 10)).slice(-2);
    var mm = ('0' + parseInt(m[2], 10)).slice(-2);
    if (m[3] && m[3] !== '00') {
      var ss = ('0' + parseInt(m[3], 10)).slice(-2);
      return hh + ':' + mm + ':' + ss;
    }
    return hh + ':' + mm;
  }

  window.filterBookingsTable = function (query) {
    var wrap = document.getElementById('bookingsTableWrap');
    if (!wrap) return;
    var tbody = wrap.querySelector('tbody');
    if (!tbody) return;
    var norm = (query || '').toLowerCase().trim();
    tbody.querySelectorAll('tr').forEach(function (tr) {
      if (!norm) {
        tr.style.display = '';
        return;
      }
      tr.style.display = tr.textContent.toLowerCase().indexOf(norm) !== -1 ? '' : 'none';
    });
  };

  function postAdminBookings(payload) {
    return fetch('admin-bookings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    }).then(function (res) {
      return res.text().then(function (text) {
        var data = parseJsonFromResponse(text);
        if (!data) {
          return { __parseError: true, __httpOk: res.ok, __raw: text };
        }
        return data;
      });
    });
  }

  window.updateBookingStatus = function (bookingId, status) {
    postAdminBookings({
      action: 'update_status',
      booking_id: bookingId,
      status: status,
    }).then(function (data) {
      if (data && data.__parseError) {
        window.showToast('Invalid response from server. Try refreshing the page.', 'error');
        return;
      }
      if (data.success) {
        window.showToast(
          status === 'confirmed' ? 'Booking confirmed.' : 'Booking cancelled.',
          'success'
        );
        location.reload();
      } else {
        window.showToast(data.message || 'Failed to update booking', 'error');
      }
    }).catch(function (err) {
      console.error(err);
      window.showToast('Network error.', 'error');
    });
  };

  function wireBookingDetailActions(booking) {
    var id = booking.id;
    var st = (booking.status || '').toLowerCase();
    var btnEdit = document.getElementById('bookingDetailBtnEdit');
    var btnConf = document.getElementById('bookingDetailBtnConfirm');
    var btnCanc = document.getElementById('bookingDetailBtnCancelBk');
    if (btnEdit) {
      btnEdit.onclick = function () {
        window.closeModal('bookingDetailModal');
        window.editBooking(id);
      };
    }
    if (btnConf) {
      btnConf.style.display = st === 'pending' ? 'inline-flex' : 'none';
      btnConf.onclick = function () {
        window.closeModal('bookingDetailModal');
        window.updateBookingStatus(id, 'confirmed');
      };
    }
    if (btnCanc) {
      btnCanc.style.display = st === 'pending' ? 'inline-flex' : 'none';
      btnCanc.onclick = function () {
        if (!confirm('Cancel this booking?')) return;
        window.closeModal('bookingDetailModal');
        window.updateBookingStatus(id, 'cancelled');
      };
    }
  }

  window.showBookingDetails = function (bookingId) {
    postAdminBookings({ action: 'get_booking', id: bookingId }).then(function (data) {
      if (data && data.__parseError) {
        window.showToast('Could not load booking. Try signing in again.', 'error');
        return;
      }
      if (!data.success) {
        window.showToast(data.message || 'Failed to load booking details', 'error');
        return;
      }
      if (!data.booking) {
        window.showToast('Booking not found.', 'error');
        return;
      }

      var booking = data.booking;
      var related = Array.isArray(data.related_bookings) ? data.related_bookings : [];

      document.getElementById('bookingDetailId').textContent =
        '#BK-' + String(booking.id).padStart(3, '0');
      document.getElementById('bookingDetailName').textContent = booking.full_name || 'Guest';
      document.getElementById('bookingDetailEvent').textContent = booking.event_name || 'N/A';
      document.getElementById('bookingDetailDate').textContent = formatBookingCalendarDate(
        booking.event_date
      );
      document.getElementById('bookingDetailTime').textContent = formatBookingTimeDisplay(
        booking.event_time,
        booking.event_time_end
      );
      document.getElementById('bookingDetailType').textContent = booking.event_type || 'N/A';
      document.getElementById('bookingDetailGuests').textContent = booking.num_guests || 'N/A';
      document.getElementById('bookingDetailAddress').textContent = booking.address || 'N/A';
      document.getElementById('bookingDetailContact').textContent =
        booking.contact_number || 'N/A';
      document.getElementById('bookingDetailEmail').textContent = booking.email_address || 'N/A';
      var notesRaw = booking.notes;
      var notesShow =
        notesRaw && String(notesRaw).trim() && String(notesRaw).trim() !== 'N/A'
          ? notesRaw
          : 'No notes';
      document.getElementById('bookingDetailNotes').textContent = notesShow;

      var st = (booking.status || '').toLowerCase();
      var badge = document.getElementById('bookingDetailStatus');
      badge.className =
        'badge badge-' +
        (st === 'confirmed' ? 'green' : st === 'cancelled' ? 'red' : 'yellow');
      badge.textContent = st ? st.charAt(0).toUpperCase() + st.slice(1) : 'N/A';

      document.getElementById('bookingDetailUserName').textContent =
        booking.user_name || 'Guest (not logged in)';
      document.getElementById('bookingDetailUserEmail').textContent = booking.user_email || 'N/A';

      var uidRow = document.getElementById('bookingDetailUserIdRow');
      var hasUid = booking.user_id != null && String(booking.user_id).trim() !== '';
      if (uidRow) uidRow.style.display = hasUid ? 'block' : 'none';
      if (hasUid) document.getElementById('bookingDetailUserId').textContent = String(booking.user_id);

      var relWrap = document.getElementById('bookingDetailRelatedWrap');
      var relList = document.getElementById('bookingDetailRelatedList');
      if (related.length === 0) {
        relWrap.style.display = 'none';
        relList.innerHTML = '';
      } else {
        relWrap.style.display = 'block';
        relList.innerHTML = related
          .map(function (r) {
            var rs = (r.status || '').toLowerCase();
            var bdg = rs === 'confirmed' ? 'green' : rs === 'cancelled' ? 'red' : 'yellow';
            var d = formatBookingCalendarDate(r.event_date);
            var tt = formatBookingTimeDisplay(r.event_time, r.event_time_end);
            var lbl = rs ? rs.charAt(0).toUpperCase() + rs.slice(1) : 'N/A';
            return (
              '<div style="margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">' +
              '<span><strong>#BK-' +
              String(r.id).padStart(3, '0') +
              '</strong> · ' +
              escapeHtml(d) +
              ' @ ' +
              escapeHtml(tt) +
              '</span>' +
              '<span class="badge badge-' +
              bdg +
              '">' +
              escapeHtml(lbl) +
              '</span></div>'
            );
          })
          .join('');
      }

      document.getElementById('bookingDetailCreated').textContent = booking.created_at
        ? new Date(booking.created_at).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
          })
        : 'N/A';
      document.getElementById('bookingDetailUpdated').textContent = booking.updated_at
        ? new Date(booking.updated_at).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
          })
        : 'N/A';

      wireBookingDetailActions(booking);
      window.openModal('bookingDetailModal');
    }).catch(function (err) {
      console.error(err);
      window.showToast('Failed to load booking details.', 'error');
    });
  };

  window.editBooking = function (bookingId) {
    postAdminBookings({ action: 'get_booking', id: bookingId }).then(function (data) {
      if (data && data.__parseError) {
        window.showToast('Could not load booking for editing.', 'error');
        return;
      }
      if (!data.success || !data.booking) {
        window.showToast((data && data.message) || 'Failed to load booking', 'error');
        return;
      }
      var booking = data.booking;

      document.getElementById('editBookingId').value = booking.id;
      document.getElementById('editEventName').value = booking.event_name || '';
      document.getElementById('editFullName').value = booking.full_name || '';
      document.getElementById('editContactNumber').value = booking.contact_number || '';
      document.getElementById('editEmailAddress').value = booking.email_address || '';
      document.getElementById('editEventDate').value = booking.event_date || '';
      document.getElementById('editEventTime').value = formatBookingTimeDisplay(
        booking.event_time,
        booking.event_time_end
      ).replace(/^N\/A$/, '');
      var sel = document.getElementById('editEventType');
      var v = booking.event_type || '';
      var has = false;
      if (sel) {
        for (var i = 0; i < sel.options.length; i++) {
          if (sel.options[i].value === v) {
            has = true;
            break;
          }
        }
        if (v && !has) {
          var opt = document.createElement('option');
          opt.value = v;
          opt.textContent = v;
          sel.appendChild(opt);
        }
        sel.value = v || '';
      }
      document.getElementById('editNumGuests').value = booking.num_guests || '';
      document.getElementById('editAddress').value = booking.address || '';
      document.getElementById('editNotes').value =
        booking.notes && String(booking.notes).trim() !== 'N/A' ? booking.notes : '';

      window.openModal('editBookingModal');
    }).catch(function (err) {
      console.error(err);
      window.showToast('Failed to load booking for editing.', 'error');
    });
  };

  window.toggleView = function () {
    var vt = document.getElementById('viewToggle');
    if (!vt) return;
    var viewMode = vt.value;
    var urlParams = new URLSearchParams(window.location.search);
    var currentMonth = urlParams.get('month') || new Date().getMonth() + 1;
    var currentYear = urlParams.get('year') || new Date().getFullYear();
    window.location.href =
      'admin-bookings.php?month=' + currentMonth + '&year=' + currentYear + '&view=' + viewMode;
  };

  window.changeYear = function () {
    var ys = document.getElementById('yearSelect');
    if (!ys) return;
    var selectedYear = ys.value;
    var urlParams = new URLSearchParams(window.location.search);
    var currentMonth = urlParams.get('month') || new Date().getMonth() + 1;
    window.location.href =
      'admin-bookings.php?month=' +
      currentMonth +
      '&year=' +
      selectedYear +
      '&view=' +
      (urlParams.get('view') || 'calendar');
  };

  window.navigateMonth = function (direction) {
    var urlParams = new URLSearchParams(window.location.search);
    var currentMonth = parseInt(urlParams.get('month'), 10) || new Date().getMonth() + 1;
    var currentYear = parseInt(urlParams.get('year'), 10) || new Date().getFullYear();
    var newMonth = currentMonth;
    var newYear = currentYear;
    if (direction === 'prev') {
      newMonth = currentMonth - 1;
      if (newMonth < 1) {
        newMonth = 12;
        newYear = currentYear - 1;
      }
    } else if (direction === 'next') {
      newMonth = currentMonth + 1;
      if (newMonth > 12) {
        newMonth = 1;
        newYear = currentYear + 1;
      }
    }
    var view = urlParams.get('view') || 'calendar';
    window.location.href =
      'admin-bookings.php?month=' + newMonth + '&year=' + newYear + '&view=' + view;
  };

  window.showDayBookings = function (day) {
    var cfg = getAdminBookingsPageConfig();
    var dateStr =
      cfg.calendarYear +
      '-' +
      String(cfg.calendarMonth).padStart(2, '0') +
      '-' +
      String(day).padStart(2, '0');

    postAdminBookings({ action: 'get_day_bookings', date: dateStr }).then(function (data) {
      if (data && data.__parseError) {
        window.showToast('Could not load day bookings.', 'error');
        return;
      }
      if (!data.success) {
        window.showToast(data.message || 'Failed to load day bookings', 'error');
        return;
      }
      var bookings = data.bookings || [];
      var modal = document.getElementById('dayBookingsModal');
      if (!modal) return;
      var title = modal.querySelector('.modal-title');
      var content = modal.querySelector('.modal-body');
      var parts = dateStr.split('-').map(Number);
      var y = parts[0];
      var mo = parts[1];
      var dNum = parts[2];
      var dateObj = new Date(y, mo - 1, dNum);
      title.innerHTML =
        '<i class="fa-solid fa-calendar-day" style="color:var(--red);margin-right:8px;"></i>Bookings for ' +
        dateObj.toLocaleDateString('en-US', {
          weekday: 'long',
          year: 'numeric',
          month: 'long',
          day: 'numeric',
        });

      if (bookings.length === 0) {
        content.innerHTML =
          '<p style="text-align: center; color: var(--muted); padding: 20px;">No bookings for this day.</p>';
      } else {
        content.innerHTML = bookings
          .map(function (booking) {
            var fullName = booking.full_name || 'Guest';
            var eventTime = formatBookingTimeDisplay(booking.event_time, booking.event_time_end);
            var eventType = booking.event_type || 'N/A';
            var numGuests = booking.num_guests || 'N/A';
            var address = booking.address || 'N/A';
            var st = booking.status || '';
            var bdg = st === 'confirmed' ? 'green' : st === 'cancelled' ? 'red' : 'yellow';
            return (
              '<div class="booking-card">' +
              '<div class="booking-id">#BK-' +
              String(booking.id).padStart(3, '0') +
              '</div>' +
              '<div class="booking-name">' +
              escapeHtml(fullName) +
              '</div>' +
              '<div class="booking-detail"><i class="fa-solid fa-clock"></i> ' +
              escapeHtml(String(eventTime)) +
              ' · ' +
              escapeHtml(String(eventType)) +
              '</div>' +
              '<div class="booking-detail"><i class="fa-solid fa-users"></i> ' +
              escapeHtml(String(numGuests)) +
              ' guests</div>' +
              '<div class="booking-detail"><i class="fa-solid fa-location-dot"></i> ' +
              escapeHtml(String(address)) +
              '</div>' +
              '<div class="booking-footer">' +
              '<span class="badge badge-' +
              bdg +
              '">' +
              escapeHtml(st) +
              '</span>' +
              '<button type="button" class="btn btn-outline btn-sm" onclick="showBookingDetails(' +
              booking.id +
              ')">View Details</button>' +
              '</div></div>'
            );
          })
          .join('');
      }
      window.openModal('dayBookingsModal');
    }).catch(function (err) {
      console.error(err);
      window.showToast('Failed to load day bookings.', 'error');
    });
  };

  window.clearAllBookings = function () {
    if (!confirm('Delete ALL bookings? This cannot be undone.')) return;
    postAdminBookings({ action: 'clear_bookings' }).then(function (data) {
      if (data && data.__parseError) {
        window.showToast('Invalid server response.', 'error');
        return;
      }
      if (data.success) {
        window.showToast('All bookings cleared.', 'success');
        location.reload();
      } else {
        window.showToast(data.message || 'Failed to clear bookings', 'error');
      }
    }).catch(function (err) {
      console.error(err);
      window.showToast('Failed to clear bookings.', 'error');
    });
  };

  document.querySelectorAll('.modal-overlay').forEach(function (o) {
    o.addEventListener('click', function (e) {
      if (e.target === o) o.classList.remove('open');
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal-overlay.open').forEach(function (o) {
      o.classList.remove('open');
    });
  });

  var searchEl = document.getElementById('bookingsTableSearch');
  if (searchEl) {
    searchEl.addEventListener('input', function () {
      window.filterBookingsTable(this.value);
    });
  }

  var newForm = document.getElementById('newBookingForm');
  if (newForm) {
    newForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var eventName = document.getElementById('newEventName').value.trim();
      var fullName = document.getElementById('newFullName').value.trim();
      var contactNumber = document.getElementById('newContactNumber').value.trim();
      var emailAddress = document.getElementById('newEmailAddress').value.trim();
      var eventDate = document.getElementById('newEventDate').value.trim();
      var eventTime = document.getElementById('newEventTime').value.trim();
      var eventType = document.getElementById('newEventType').value.trim();
      var numGuests = document.getElementById('newNumGuests').value.trim();
      var address = document.getElementById('newAddress').value.trim();
      var notes = document.getElementById('newNotes').value.trim() || 'N/A';

      if (
        !eventName ||
        !fullName ||
        !contactNumber ||
        !emailAddress ||
        !eventDate ||
        !eventTime ||
        !eventType ||
        !numGuests ||
        !address
      ) {
        window.showToast('Please fill in all required fields', 'error');
        return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailAddress)) {
        window.showToast('Please enter a valid email address', 'error');
        return;
      }
      if (contactNumber.length < 10) {
        window.showToast('Please enter a valid contact number', 'error');
        return;
      }

      postAdminBookings({
        action: 'create_booking',
        eventName: eventName,
        fullName: fullName,
        contactNumber: contactNumber,
        emailAddress: emailAddress,
        eventDate: eventDate,
        eventTime: eventTime,
        eventType: eventType,
        numGuests: numGuests,
        address: address,
        notes: notes,
        userEmail: 'admin@lukesseafood.com',
        userName: 'Admin',
      }).then(function (data) {
        if (data && data.__parseError) {
          window.showToast('Server error while creating booking.', 'error');
          return;
        }
        if (data.success) {
          window.showToast('Booking created successfully!', 'success');
          window.closeModal('newBookingModal');
          newForm.reset();
          setTimeout(function () {
            location.reload();
          }, 600);
        } else {
          window.showToast(data.message || 'Failed to create booking', 'error');
        }
      }).catch(function (err) {
        console.error(err);
        window.showToast('Failed to create booking.', 'error');
      });
    });
  }

  var editForm = document.getElementById('editBookingForm');
  if (editForm) {
    editForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var bookingId = document.getElementById('editBookingId').value.trim();
      var formData = {
        action: 'update_booking',
        booking_id: bookingId,
        eventName: document.getElementById('editEventName').value.trim(),
        fullName: document.getElementById('editFullName').value.trim(),
        contactNumber: document.getElementById('editContactNumber').value.trim(),
        emailAddress: document.getElementById('editEmailAddress').value.trim(),
        eventDate: document.getElementById('editEventDate').value.trim(),
        eventTime: document.getElementById('editEventTime').value.trim(),
        eventType: document.getElementById('editEventType').value.trim(),
        numGuests: document.getElementById('editNumGuests').value.trim(),
        address: document.getElementById('editAddress').value.trim(),
        notes: document.getElementById('editNotes').value.trim() || 'N/A',
      };

      if (
        !formData.eventName ||
        !formData.fullName ||
        !formData.contactNumber ||
        !formData.emailAddress ||
        !formData.eventDate ||
        !formData.eventTime ||
        !formData.eventType ||
        !formData.numGuests ||
        !formData.address
      ) {
        window.showToast('Please fill in all required fields', 'error');
        return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.emailAddress)) {
        window.showToast('Please enter a valid email address', 'error');
        return;
      }
      if (formData.contactNumber.length < 10) {
        window.showToast('Please enter a valid contact number', 'error');
        return;
      }

      postAdminBookings(formData).then(function (data) {
        if (data && data.__parseError) {
          window.showToast('Server error while updating.', 'error');
          return;
        }
        if (data.success) {
          window.showToast('Booking updated successfully!', 'success');
          window.closeModal('editBookingModal');
          setTimeout(function () {
            location.reload();
          }, 600);
        } else {
          window.showToast(data.message || 'Failed to update booking', 'error');
        }
      }).catch(function (err) {
        console.error(err);
        window.showToast('Failed to update booking.', 'error');
      });
    });
  }
})();
