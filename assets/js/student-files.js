document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.student-files-filters');
    if (!form) return;

    const grade = document.getElementById('student-files-grade');
    const section = document.getElementById('student-files-section');
    grade.addEventListener('change', function () {
        section.value = '0';
        form.submit();
    });
    section.addEventListener('change', function () {
        form.submit();
    });

    const printButton = document.querySelector('.student-files-print');
    if (printButton) {
        printButton.addEventListener('click', function () { window.print(); });
    }
});
