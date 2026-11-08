# Simulator Finder external-link regression

## Automated checks

```bash
php tests/simulator_links_test.php
# Optional: repeat using the real html_writer and s() from an installed Moodle site.
MOODLE_TEST_CONFIG=/path/to/moodle/config.php php tests/simulator_links_test.php
```

The standalone test uses controlled Moodle renderer doubles; the optional mode uses Moodle's actual renderer. Both modes were run. The test is picked up by Plugin CI's existing `tests/*_test.php` loop.

Coverage includes Markdown links, raw URLs, URL-as-label and empty-label fallbacks, query-string ampersands, literal HTML/quotes in labels, multiple links, surrounding punctuation, supported bold text, and empty input. Each link must preserve its destination, have one new-tab announcement, use `_blank` and `noopener noreferrer`, and contain no markup from its label.

The original draft fails the first query-string/special-character case: escaping the whole line before passing its captures through the link renderer produces `&amp;amp;` in the URL and label. The revised formatter tokenises unescaped text once, escapes plain segments and labels once, and never rescans generated HTML as input.

## Disposable Moodle integration check

Validation environment: Moodle 4.5.15 (Build 20261005), PHP 8.3.33, PostgreSQL 15.18, Chromium headless on Linux. Only synthetic course material and test users were used.

1. Enrol a teacher with material-management permission in a disposable course with a readable course resource.
2. Configure a local controlled custom HTTP provider and explicitly approve only the synthetic material for that provider. Keep web search disabled.
3. Return a simulator response containing these fixture links in the result sections:

```text
[Circuit & <tool>](https://example.test/sim?a=1&b=2)
https://example.test/raw?a=1&b=2.
[https://example.test/source?a=1&b=2](https://example.test/source?a=1&b=2)
```

4. Open the actual Simulator Finder page as the teacher, select the resource and submit generation.
5. Verify all three rendered anchors retain the exact query strings, including a literal `&` in each DOM href. The first label is literal `Circuit & <tool>`; raw and URL-as-label links use `Open simulator or tool website`. Each ends in `(opens in a new tab)`.
6. Verify `_blank`, `noopener noreferrer`, no nested/executable label markup, and preserved trailing punctuation outside the raw URL.

This full teacher POST and result-rendering path passed with the controlled provider. The direct search-result link path uses the same tested helper; a real external search provider was not exercised. Live external destinations were not opened. Actual screen-reader speech, other Moodle versions/themes and the full Moodle PHPUnit/Behat or Marketplace suite were not tested locally.
