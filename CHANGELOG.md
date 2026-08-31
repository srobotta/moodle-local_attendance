# Changelog moodle-local_attendance

## 5.2-r3

- New parameters `aftermodule` and `beforemodule` for creating an activity
at a certain position in the course.
- Fix the handling of data so that page and url activity can be imported
without errors.
- Font Awesome True Type Font file is on a different location in 5.3.
- Update Moodle CI with latest required DB versions (mainly for upcoming 5.3).
- Because of MDL-75067 behat tests with two file uploads work now in core, so
the special testcase with the workaround has been removed in this plugin.

## 5.2-r2

- Implemented changes that were required during the review process.

## 5.2-r1

First official release after being more or less feature and test complete
of the basic use case creating attendance courses via CSV upload.