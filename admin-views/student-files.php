<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="olama-student-files" dir="<?php echo esc_attr($direction); ?>">
    <div class="student-files-heading">
        <h2><?php echo esc_html($label('Student Files')); ?></h2>
        <p class="description"><?php echo esc_html($label('Student details, school enrollment history, and siblings by grade and section.')); ?></p>
    </div>

    <?php if (!$available): ?>
        <div class="notice notice-warning"><p><?php echo esc_html($label('Student files are unavailable. Check that Olama Core student, family, and enrollment data is installed.')); ?></p></div>
    <?php elseif (!$active_year): ?>
        <div class="notice notice-warning"><p><?php echo esc_html($label('Set an active academic year in Olama Core before viewing student files.')); ?></p></div>
    <?php else: ?>
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="student-files-filters card">
            <input type="hidden" name="page" value="olama-student-evaluation">
            <input type="hidden" name="tab" value="student_files">
            <div class="student-files-filter">
                <label for="student-files-year"><?php echo esc_html($label('Academic Year')); ?></label>
                <input id="student-files-year" type="text" value="<?php echo esc_attr($active_year->year_name); ?>" readonly>
            </div>
            <div class="student-files-filter">
                <label for="student-files-grade"><?php echo esc_html($label('Grade')); ?></label>
                <select id="student-files-grade" name="grade_id">
                    <option value="0"><?php echo esc_html($label('Select Grade')); ?></option>
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo esc_attr($grade->id); ?>" <?php selected($grade_id, $grade->id); ?>><?php echo esc_html($grade->grade_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="student-files-filter">
                <label for="student-files-section"><?php echo esc_html($label('Section')); ?></label>
                <select id="student-files-section" name="section_id" <?php disabled(empty($sections)); ?>>
                    <option value="0"><?php echo esc_html($label('Select Section')); ?></option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo esc_attr($section->id); ?>" <?php selected($section_id, $section->id); ?>><?php echo esc_html($section->section_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="button button-primary"><?php echo esc_html($label('View Report')); ?></button>
        </form>

        <?php if ($report_error): ?>
            <div class="notice notice-error"><p><?php echo esc_html($label('Unable to load student files. Please try again.')); ?></p></div>
        <?php elseif (!$grade_id || !$section_id): ?>
            <div class="notice notice-info"><p><?php echo esc_html($label('Select a grade and section to view student files.')); ?></p></div>
        <?php else: ?>
            <div class="student-files-report">
                <div class="student-files-toolbar">
                    <div>
                        <h3><?php echo esc_html($label('Student files report')); ?></h3>
                        <p><bdi><?php echo esc_html($active_year->year_name); ?></bdi> &middot; <?php echo esc_html($placement($selected_grade->grade_name, $selected_section->section_name)); ?> &middot; <?php echo esc_html($label('Student count')); ?>: <?php echo esc_html(count($students)); ?></p>
                    </div>
                    <?php if ($students): ?>
                        <button type="button" class="button student-files-print"><span class="dashicons dashicons-printer" aria-hidden="true"></span> <?php echo esc_html($label('Print Report')); ?></button>
                    <?php endif; ?>
                </div>
                <?php if (!$students): ?>
                    <p class="student-files-empty"><?php echo esc_html($label('No students found in this section.')); ?></p>
                <?php else: ?>
                    <div class="student-files-table-scroll" role="region" aria-label="<?php echo esc_attr($label('Student files report')); ?>" tabindex="0">
                        <table class="wp-list-table widefat striped student-files-table">
                            <thead>
                                <tr>
                                    <?php foreach (array('Student ID', 'Student Name', 'National ID', 'Place of Birth', 'Date of Birth', 'Father Mobile', 'Mother Mobile', 'Family Address', 'Current Grade / Section', 'Previous Grades / Sections', 'Siblings in School') as $column): ?>
                                        <th scope="col"><?php echo esc_html($label($column)); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td class="student-files-id"><bdi dir="ltr"><?php echo esc_html($display_value($student->student_uid)); ?></bdi></td>
                                        <td class="student-files-name"><strong><?php echo esc_html($display_value($student->student_name)); ?></strong></td>
                                        <td><bdi dir="ltr"><?php echo esc_html($display_value($student->national_id)); ?></bdi></td>
                                        <td><?php echo esc_html($display_value($student->birth_place)); ?></td>
                                        <td><bdi dir="ltr"><?php
                                            $birth_date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $student->birth_date);
                                            echo esc_html($birth_date && $birth_date->format('Y-m-d') === $student->birth_date && '0000-00-00' !== $student->birth_date
                                                ? $birth_date->format('d/m/Y') : $label('Not recorded'));
                                        ?></bdi></td>
                                        <td><bdi dir="ltr"><?php echo esc_html($display_value($student->father_mobile)); ?></bdi></td>
                                        <td><bdi dir="ltr"><?php echo esc_html($display_value($student->mother_mobile)); ?></bdi></td>
                                        <td class="student-files-address"><?php echo esc_html($display_value($student->family_address)); ?></td>
                                        <td>
                                            <?php echo esc_html($placement($student->current_grade, $student->current_section)); ?>
                                            <span class="student-files-year"><bdi><?php echo esc_html($student->study_year); ?></bdi></span>
                                        </td>
                                        <td>
                                            <?php if (!$student->history): ?>
                                                <span class="description"><?php echo esc_html($label('No previous enrollment recorded.')); ?></span>
                                            <?php else: ?>
                                                <ul class="student-files-list">
                                                    <?php foreach ($student->history as $enrollment): ?>
                                                        <li><?php echo esc_html($placement($enrollment->grade_name, $enrollment->section_name)); ?><span class="student-files-year"><bdi><?php echo esc_html($enrollment->study_year); ?></bdi></span></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$student->siblings): ?>
                                                <span class="description"><?php echo esc_html($label('None recorded')); ?></span>
                                            <?php else: ?>
                                                <ul class="student-files-list">
                                                    <?php foreach ($student->siblings as $sibling): ?>
                                                        <li><?php echo esc_html($sibling); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="description student-files-note"><?php echo esc_html($label('Previous placements reflect imported school years. Siblings include students enrolled in the current school year.')); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
