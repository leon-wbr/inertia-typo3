-- The conformance case pages, one per case. Loaded by
-- Build/Scripts/conformance.sh after `typo3 setup`. Components and props
-- come from EXT:inertia_conformance (Classes/EventListener/CasePages.php),
-- by slug.

INSERT INTO pages (uid, pid, sorting, doktype, is_siteroot, title, slug) VALUES
  (1, 0, 256, 1, 1, 'Home', '/'),
  (2, 1, 256, 1, 0, 'Dump', '/dump'),
  (3, 2, 256, 1, 0, 'Other', '/dump/other'),
  (4, 1, 512, 1, 0, 'Merge', '/merge'),
  (5, 1, 768, 1, 0, 'Once', '/once'),
  (6, 5, 256, 1, 0, 'Once other', '/once/other'),
  (7, 1, 1024, 1, 0, 'Deferred', '/deferred'),
  (8, 7, 256, 1, 0, 'Rescue', '/deferred/rescue'),
  (9, 1, 1280, 1, 0, 'When visible', '/when-visible'),
  (10, 1, 1536, 1, 0, 'Scroll', '/scroll'),
  (11, 1, 1792, 1, 0, 'Poll', '/poll'),
  (12, 1, 2048, 1, 0, 'Form', '/form'),
  (13, 1, 2304, 1, 0, 'Remember', '/remember'),
  (14, 1, 2560, 1, 0, 'History', '/history'),
  (15, 14, 256, 1, 0, 'Encrypted', '/history/encrypted'),
  (16, 14, 512, 1, 0, 'Clear', '/history/clear');
