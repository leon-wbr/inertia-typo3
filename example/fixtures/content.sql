-- Pages and content of the example site. Loaded by `ddev setup-example`
-- after `typo3 setup` has created the schema.
-- Frontend layout (pages.layout): 1 = Features page, 2 = Contact page.

INSERT INTO pages (uid, pid, sorting, doktype, is_siteroot, title, slug, layout, nav_hide, hidden, sys_language_uid, l10n_parent) VALUES
  (1, 0, 256, 1, 1, 'Home', '/', 0, 0, 0, 0, 0),
  (2, 1, 256, 1, 0, 'Features', '/features', 1, 0, 0, 0, 0),
  (3, 1, 512, 1, 0, 'Contact', '/contact', 2, 0, 0, 0, 0),
  (4, 1, 768, 1, 0, 'Not in menu', '/not-in-menu', 0, 1, 0, 0, 0),
  (5, 1, 1024, 1, 0, 'Hidden page', '/hidden-page', 0, 0, 1, 0, 0),
  (11, 0, 256, 1, 1, 'Startseite', '/', 0, 0, 0, 1, 1),
  (12, 1, 256, 1, 0, 'Funktionen', '/funktionen', 1, 0, 0, 1, 2),
  (13, 1, 512, 1, 0, 'Kontakt', '/kontakt', 2, 0, 0, 1, 3);

INSERT INTO tt_content (uid, pid, sorting, colPos, CType, header, bodytext, sys_language_uid, l18n_parent) VALUES
  (1, 1, 256, 0, 'header', 'Welcome to TYPO3 and Inertia', '', 0, 0),
  (2, 1, 512, 0, 'text', 'About this example',
    '<p>TYPO3 renders pages, Inertia and React render the frontend. See the <a href="t3://page?uid=2">features</a> or <a href="t3://page?uid=3">get in touch</a>.</p>', 0, 0),
  (3, 2, 256, 0, 'text', 'Prop types', '<p>Deferred, optional and merge props from the adapter.</p>', 0, 0),
  (4, 3, 256, 0, 'text', 'Get in touch', '<p>Send the form to see an Inertia redirect.</p>', 0, 0),
  (5, 4, 256, 0, 'text', 'Not in menu', '<p>This page is reachable, but hidden in menus.</p>', 0, 0),
  (11, 1, 256, 0, 'header', 'Willkommen bei TYPO3 und Inertia', '', 1, 1),
  (12, 1, 512, 0, 'text', 'Über dieses Beispiel',
    '<p>TYPO3 rendert die Seiten, Inertia und React das Frontend. Zu den <a href="t3://page?uid=2">Funktionen</a>.</p>', 1, 2),
  (13, 2, 256, 0, 'text', 'Prop-Typen', '<p>Deferred, optional und merge props des Adapters.</p>', 1, 3),
  (14, 3, 256, 0, 'text', 'Kontakt aufnehmen', '<p>Schick das Formular ab.</p>', 1, 4);
