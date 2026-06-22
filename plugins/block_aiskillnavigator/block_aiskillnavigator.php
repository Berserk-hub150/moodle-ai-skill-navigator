<?php
defined('MOODLE_INTERNAL') || die();

class block_aiskillnavigator extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_aiskillnavigator');
    }

    public function applicable_formats() {
        return [
            'course-view' => true,
            'site-index' => false,
            'my' => false,
        ];
    }

    public function get_content() {
        global $COURSE, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        if (empty($COURSE) || empty($COURSE->id) || (int)$COURSE->id <= 1) {
            $this->content->text = html_writer::div(
                get_string('nocourse', 'block_aiskillnavigator'),
                'alert alert-info'
            );
            return $this->content;
        }

        $courseid = (int)$COURSE->id;
        $context = context_course::instance($courseid);

        $isteacher = is_siteadmin()
            || has_capability('local/aiskillnavigator:viewteacher', $context)
            || has_capability('moodle/course:update', $context)
            || has_capability('moodle/course:manageactivities', $context);

        $isstudent = is_siteadmin()
            || has_capability('local/aiskillnavigator:viewstudent', $context)
            || has_capability('moodle/course:view', $context);

        $html = '';
        $html .= html_writer::start_div('aisn-block-wrapper', [
            'style' => 'padding:10px;'
        ]);

        $html .= html_writer::tag('h4', 'AI Skill Navigator', [
            'style' => 'font-weight:800;margin-bottom:10px;'
        ]);

        $html .= html_writer::tag('p', 'Course-aware AI tools for students and teachers.', [
            'style' => 'font-size:13px;color:#475569;margin-bottom:12px;'
        ]);

        $html .= $this->link_button(
            'Open AI Skill Navigator',
            '/local/aiskillnavigator/index.php',
            $courseid,
            'btn btn-primary btn-block'
        );

        if ($isteacher) {
            $html .= $this->section_title('Teacher tools');

            $html .= $this->link_button('Teacher dashboard', '/local/aiskillnavigator/pages/teacher.php', $courseid, 'btn btn-outline-secondary btn-block');
            $html .= $this->link_button('Tutor analyst', '/local/aiskillnavigator/pages/tutor_analytics.php', $courseid, 'btn btn-outline-info btn-block');
            $html .= $this->link_button('Initial/final tests', '/local/aiskillnavigator/pages/teacher_assessments.php', $courseid, 'btn btn-outline-success btn-block');
            $html .= $this->link_button('Learning-gap analysis', '/local/aiskillnavigator/pages/gap_analysis.php', $courseid, 'btn btn-outline-warning btn-block');
            $html .= $this->link_button('AI Course Builder', '/local/aiskillnavigator/pages/course_builder.php', $courseid, 'btn btn-outline-info btn-block');
            $html .= $this->link_button('AI Simulator Finder', '/local/aiskillnavigator/pages/simulator_finder.php', $courseid, 'btn btn-outline-info btn-block');

            $html .= $this->section_title('Knowledge base');
            $html .= $this->link_button('Course materials / RAG', '/local/aiskillnavigator/pages/teacher_materials.php', $courseid, 'btn btn-outline-success btn-block');

            $html .= $this->safe_ocr_toggle($courseid);

            $html .= html_writer::tag(
                'p',
                'RAG-ready AI support for Moodle courses.',
                ['style' => 'font-size:12px;color:#64748b;margin-top:10px;']
            );
        }

        if ($isstudent) {
            $html .= $this->section_title('Student tools');

            $html .= $this->link_button('AI Tutor', '/local/aiskillnavigator/pages/tutor.php', $courseid, 'btn btn-outline-primary btn-block');
            $html .= $this->link_button('Assessment', '/local/aiskillnavigator/pages/assessment.php', $courseid, 'btn btn-outline-success btn-block');
            $html .= $this->link_button('Adaptive review', '/local/aiskillnavigator/pages/adaptive_review.php', $courseid, 'btn btn-outline-warning btn-block');
            $html .= $this->link_button('Quiz generator', '/local/aiskillnavigator/pages/quizgenerator.php', $courseid, 'btn btn-outline-info btn-block');
            $html .= $this->link_button('Mind map generator', '/local/aiskillnavigator/pages/mindmapgenerator.php', $courseid, 'btn btn-outline-secondary btn-block');
        }

        if (!$isstudent && !$isteacher) {
            $html .= html_writer::div('No AI tools available for your role in this course.', 'alert alert-info');
        }

        $html .= html_writer::end_div();

        $this->content->text = $html;
        return $this->content;
    }

    private function section_title(string $title): string {
        return html_writer::tag('div', s($title), [
            'style' => 'font-weight:800;font-size:12px;text-transform:uppercase;margin-top:14px;margin-bottom:8px;color:#111827;'
        ]);
    }

    private function link_button(string $label, string $path, int $courseid, string $class): string {
        $url = new moodle_url($path, ['courseid' => $courseid]);

        return html_writer::link($url, s($label), [
            'class' => $class,
            'style' => 'margin-bottom:8px;width:100%;'
        ]);
    }

    private function safe_ocr_toggle(int $courseid): string {
        global $CFG;

        try {
            $helper = $CFG->dirroot . '/local/aiskillnavigator/includes/document_ocr_toggle_helper.php';

            if (!file_exists($helper)) {
                return '';
            }

            require_once($helper);

            if (!function_exists('local_aisn_render_sidebar_ocr_toggle_button')) {
                return '';
            }

            return local_aisn_render_sidebar_ocr_toggle_button($courseid);
        } catch (Throwable $e) {
            return html_writer::div(
                'OCR toggle non disponibile.',
                'alert alert-warning',
                ['style' => 'font-size:12px;margin-top:10px;']
            );
        }
    }
}