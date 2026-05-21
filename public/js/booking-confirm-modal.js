/**
 * Custom confirm dialogs for booking actions (admin + customer).
 * Attach to forms with data-confirm-message (or legacy data-confirm).
 */
(function () {
  'use strict';

  var ICONS = {
    confirm: 'fa-check-circle',
    decline: 'fa-times-circle',
    cancel: 'fa-ban',
    refund: 'fa-undo',
    warning: 'fa-exclamation-triangle',
    danger: 'fa-exclamation-circle',
  };

  function getMessage(form) {
    return form.getAttribute('data-confirm-message')
      || form.getAttribute('data-confirm')
      || 'Are you sure you want to continue?';
  }

  function closeModal(overlay, onDone) {
    if (!overlay || !overlay.parentNode) {
      if (onDone) onDone();
      return;
    }
    overlay.classList.add('booking-confirm-overlay--closing');
    var modal = overlay.querySelector('.booking-confirm-modal');
    if (modal) {
      modal.classList.add('booking-confirm-modal--closing');
    }
    setTimeout(function () {
      overlay.remove();
      if (onDone) onDone();
    }, 200);
  }

  function showConfirmModal(options) {
    var theme = options.theme === 'customer' ? 'customer' : 'admin';
    var variant = options.variant || 'warning';
    var icon = ICONS[variant] || ICONS.warning;

    document.querySelectorAll('.booking-confirm-overlay').forEach(function (el) {
      el.remove();
    });

    var overlay = document.createElement('div');
    overlay.className = 'booking-confirm-overlay booking-confirm-overlay--' + theme;
    overlay.setAttribute('role', 'presentation');

    var modal = document.createElement('div');
    modal.className = 'booking-confirm-modal booking-confirm-modal--' + theme
      + ' booking-confirm-modal--' + variant;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'booking-confirm-title');

    modal.innerHTML =
      '<div class="booking-confirm-modal__icon" aria-hidden="true"><i class="fas ' + icon + '"></i></div>'
      + '<h3 id="booking-confirm-title" class="booking-confirm-modal__title"></h3>'
      + '<p class="booking-confirm-modal__message"></p>'
      + '<div class="booking-confirm-modal__actions">'
      + '  <button type="button" class="booking-confirm-modal__btn booking-confirm-modal__btn--secondary" data-confirm-dismiss></button>'
      + '  <button type="button" class="booking-confirm-modal__btn booking-confirm-modal__btn--primary" data-confirm-ok></button>'
      + '</div>';

    modal.querySelector('.booking-confirm-modal__title').textContent = options.title || 'Confirm action';
    modal.querySelector('.booking-confirm-modal__message').textContent = options.message || '';
    modal.querySelector('[data-confirm-dismiss]').textContent = options.cancelLabel || 'Cancel';
    modal.querySelector('[data-confirm-ok]').textContent = options.okLabel || 'Confirm';

    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';

    var primaryBtn = modal.querySelector('[data-confirm-ok]');
    var dismissBtn = modal.querySelector('[data-confirm-dismiss]');

    function releaseScroll() {
      if (!document.querySelector('.booking-confirm-overlay')) {
        document.body.style.overflow = '';
      }
    }

    function confirm() {
      closeModal(overlay, function () {
        releaseScroll();
        if (typeof options.onConfirm === 'function') {
          options.onConfirm();
        }
      });
    }

    function dismiss() {
      closeModal(overlay, releaseScroll);
    }

    primaryBtn.addEventListener('click', confirm);
    dismissBtn.addEventListener('click', dismiss);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        dismiss();
      }
    });

    function onKey(e) {
      if (e.key === 'Escape') {
        dismiss();
        document.removeEventListener('keydown', onKey);
      }
    }
    document.addEventListener('keydown', onKey);

    setTimeout(function () {
      primaryBtn.focus();
    }, 50);
  }

  function bindForm(form) {
    if (form.dataset.bookingConfirmBound === '1') {
      return;
    }
    form.dataset.bookingConfirmBound = '1';

    form.addEventListener('submit', function (e) {
      if (form.dataset.bookingConfirmSkip === '1') {
        form.dataset.bookingConfirmSkip = '0';
        return;
      }

      e.preventDefault();

      var theme = form.getAttribute('data-confirm-theme') || 'admin';
      var variant = form.getAttribute('data-confirm-variant') || 'warning';

      showConfirmModal({
        theme: theme,
        variant: variant,
        title: form.getAttribute('data-confirm-title') || 'Confirm action',
        message: getMessage(form),
        okLabel: form.getAttribute('data-confirm-ok') || 'Confirm',
        cancelLabel: form.getAttribute('data-confirm-cancel') || 'Go back',
        onConfirm: function () {
          form.dataset.bookingConfirmSkip = '1';
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
        },
      });
    });
  }

  function init() {
    var selector = 'form[data-confirm-message], form[data-confirm], form.js-booking-confirm-form';
    document.querySelectorAll(selector).forEach(bindForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.BookingConfirmModal = { show: showConfirmModal, init: init };
})();
