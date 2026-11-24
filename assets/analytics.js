(() => {
  const settings = document.currentScript.dataset;
  const key = 'dobi-analytics-consent';
  let active = false;
  const page = {category_id: settings.category, publication_id: settings.publication};
  function start() {
    if (active) return;
    active = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    gtag('js', new Date());
    gtag('config', settings.id, {
      send_page_view: false,
      page_location: location.origin + location.pathname,
      page_referrer: '',
      allow_google_signals: false,
      allow_ad_personalization_signals: false
    });
    const tag = document.createElement('script');
    tag.async = true;
    tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(settings.id);
    document.head.append(tag);
    if (settings.event) gtag('event', settings.event, page);
  }
  function remember(value) {
    try { localStorage.setItem(key, value); } catch {}
  }
  let consent;
  try { consent = localStorage.getItem(key); } catch {}
  if (consent === 'yes') start();
  const settingsButton = document.createElement('button');
  settingsButton.type = 'button';
  settingsButton.textContent = 'Analytics settings';
  (document.querySelector('footer') || document.body).append(settingsButton);
  function choices() {
    if (document.getElementById('dobi-consent')) return;
    const panel = document.createElement('div');
    panel.id = 'dobi-consent';
    panel.setAttribute('role', 'group');
    panel.setAttribute('aria-label', 'Analytics consent');
    panel.textContent = 'Allow optional usage analytics? ';
    for (const [label, value] of [['Allow', 'yes'], ['Decline', 'no']]) {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = label;
      button.addEventListener('click', () => {
        remember(value);
        if (value === 'yes') {
          window['ga-disable-' + settings.id] = false;
          start();
          active = true;
        } else {
          window['ga-disable-' + settings.id] = true;
          active = false;
        }
        panel.remove();
      });
      panel.append(button);
    }
    document.body.append(panel);
  }
  settingsButton.addEventListener('click', choices);
  if (!consent) choices();
  document.addEventListener('click', (event) => {
    if (!active || !(event.target instanceof Element)) return;
    const link = event.target.closest('a[href]');
    const tagged = event.target.closest('[data-dobi-event]');
    if (link?.hostname === 'wa.me') gtag('event', 'whatsapp_click', page);
    if (link?.hostname === 't.me') gtag('event', 'telegram_click', page);
    if (tagged && ['commercial_enquiry','related_publication_click','tool_interaction'].includes(tagged.dataset.dobiEvent)) {
      gtag('event', tagged.dataset.dobiEvent, {
        category_id: tagged.dataset.dobiCategory || page.category_id,
        publication_id: tagged.dataset.dobiPublication || page.publication_id
      });
    }
  });
})();
