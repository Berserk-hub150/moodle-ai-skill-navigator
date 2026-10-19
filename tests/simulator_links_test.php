<?php
// Standalone regression test; MOODLE_TEST_CONFIG optionally uses the real Moodle renderer.
if (getenv('MOODLE_TEST_CONFIG')) {
    define('CLI_SCRIPT', true);
    require(getenv('MOODLE_TEST_CONFIG'));
} else {
    define('MOODLE_INTERNAL', true);
    function s($text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    class html_writer {
        public static function link($url, $text, $attributes): string {
            $html = '<a href="' . s($url) . '"';
            foreach ($attributes as $key => $value) {
                $html .= ' ' . $key . '="' . s($value) . '"';
            }
            return $html . '>' . $text . '</a>';
        }
    }
}
require_once(__DIR__ . '/../plugins/aiskillnavigator/includes/simulator_links_helper.php');
function check_link(string $html, string $url, string $label): void {
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    $links = $doc->getElementsByTagName('a');
    if ($links->length !== 1) {
        throw new RuntimeException('Expected exactly one link: ' . $html);
    }
    $link = $links->item(0);
    foreach (['href' => $url, 'target' => '_blank', 'rel' => 'noopener noreferrer'] as $name => $value) {
        if ($link->getAttribute($name) !== $value) {
            throw new RuntimeException($name . ' mismatch: ' . $html);
        }
    }
    if ($link->textContent !== $label . ' (opens in a new tab)') {
        throw new RuntimeException('Label mismatch: ' . $html);
    }
    if ($doc->getElementsByTagName('script')->length || $link->getElementsByTagName('*')->length) {
        throw new RuntimeException('Labels must be text, not executable markup: ' . $html);
    }
}
$url = 'https://example.test/sim?a=1&b=2';
check_link(local_aiskillnavigator_sim_inline_format('[Circuit & <tool>](' . $url . ')'), $url, 'Circuit & <tool>');
check_link(local_aiskillnavigator_sim_inline_format('Try ' . $url . '.'), $url, 'Open simulator or tool website');
check_link(local_aiskillnavigator_sim_inline_format('[' . $url . '](' . $url . ')'), $url, 'Open simulator or tool website');
check_link(local_aiskillnavigator_sim_external_link($url, ''), $url, 'Open simulator or tool website');
check_link(local_aiskillnavigator_sim_external_link($url, '<script>alert("x")</script>'), $url, '<script>alert("x")</script>');
$punctuated = local_aiskillnavigator_sim_inline_format('Try https://example.test/sim.,;');
if (!str_ends_with($punctuated, '</a>.,;')) {
    throw new RuntimeException('Punctuation outside a raw URL must be preserved: ' . $punctuated);
}
$multiple = local_aiskillnavigator_sim_inline_format('[One](https://example.test/1) https://example.test/2');
if (substr_count($multiple, '<a ') !== 2 || substr_count($multiple, '(opens in a new tab)') !== 2) {
    throw new RuntimeException('Every link must have one announcement: ' . $multiple);
}
$plain = local_aiskillnavigator_sim_inline_format('**Tools** & <script>alert(1)</script>');
if ($plain !== '<strong>Tools</strong> &amp; &lt;script&gt;alert(1)&lt;/script&gt;') {
    throw new RuntimeException('Escape plain text and preserve supported bold markup: ' . $plain);
}
if (local_aiskillnavigator_sim_inline_format('  ') !== '') {
    throw new RuntimeException('Empty input must stay empty');
}
echo "simulator_links_test: OK\n";
