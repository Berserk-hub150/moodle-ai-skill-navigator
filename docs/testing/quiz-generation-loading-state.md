# Quiz generation loading-state regression

## Automated checks

```bash
node tests/quiz_generator_test.js
python3 tests/embedded_javascript_test.py
find plugins tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

The standalone JavaScript test runs the production asset with controlled DOM doubles. It checks delayed DOM initialisation, already-canceled and later-canceled submissions, native-navigation preservation, a second submit event, the busy label/status, history restoration and retry, and a fresh response. Plugin CI runs it alongside the existing material-selector test.

The original draft fails the canceled-submission regression: it disables the button even when another listener has canceled submission. The revised handler does not lock that form. A second submit event is canceled while the first is in flight.

## Disposable Moodle integration check

Validation environment: Moodle 4.5.15 (Build 20261005), PHP 8.3.33, PostgreSQL 15.18, Chromium headless on Linux. Only synthetic course material and test users were used. The normal success path used the built-in prototype provider; a local controlled HTTP provider returned HTTP 503 for the failure case. No real student records or paid AI provider were used.

Reproduce in a disposable course with an enrolled student and a readable course resource:

1. Open Quiz Generator and select the resource. Generate using the mouse, then repeat using Enter in the topic field. Verify one POST for each action and a generated quiz.
2. Observe the submit event: the button is disabled and labeled `Generating quiz...`, the form is busy, and the initially empty status region receives the progress message. It stays outside the busy form and is never hidden with `display:none`.
3. Dispatch a second cancelable submit event while the first is in flight; it must be canceled. A rejected duplicate must not unlock the original submission.
4. Cancel submission from another validation listener. Verify the original button label and enabled state return, the progress message clears, and retry is possible. Test native HTML constraint validation too (the automated browser run used a required-field constraint fixture).
5. In a test fixture, submit an invalid sesskey, observe Moodle's validation error, then use browser Back. Verify the restored form is enabled and the progress message is cleared.
6. Use a controlled failing provider. Verify the server-rendered error response has an enabled generation button and empty progress status. Restore the prototype provider and retry successfully.

These mouse/keyboard, server-validation, provider-failure and retry checks were run against the actual Moodle page, not a static HTML copy. Browser event observations verified the status text and ARIA attributes; Chromium's accessibility tree exposed the polite status live region. The standalone test separately exercises a persisted `pageshow` event.

Actual speech output from NVDA, VoiceOver or another screen reader was not tested. Other Moodle versions, themes and browsers still need their own validation. This is not a claim that the full Moodle PHPUnit/Behat or Marketplace suite was run locally.
