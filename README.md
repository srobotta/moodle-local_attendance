# Moodle Plugin Attendance Course Creator

The use case for this plugin is to have automatically set up a course
to check the student attendance by having them answer one question
in a test activity to check for the students precense in the course.

The plugin was developed in a generic matter, so that different
activities can be created with it and that other use cases are possible
as well not just the "attendance check" use case only.

## Generate attendance courses

The idea of automatically track attendance of a course is done via a specific
Moodle course. The tracking is not done in the course directly that
is used for teaching, because the completion tracking depends on the
presence only and not of the assessements that a student must fulfill.
Therefore, to track the attendance of a course, a parallel Moodle course
is required that only tracks the presence and awards the studend a badge
upon successful course completion (e.g. when a certain threshold of presence
items is reached). This is done by presenting the students a simple
quiz with one question that they must answer successfully so that their
presence is counted. The quiz is open for the course presence time only
and is password protected.

The plugin offers an upload form so that managers can prepare an import
file with the course data to automatically create such courses.

## Import file format

The file format for creating one or more such presence check courses
with the presence dates is like a CSV but with some extra handling.

At the beginning of each line, the first column consists of a command
which can be:

* COURSE_COLUMNS
* MODULE_COLUMNS
* BADGE_COLUMNS
* COURSE
* MODULE
* USE_COURSE
* BADGE

The first commands `*_COLUMNS` define the field names of the following
`COURSE`, `MODULE` and `BADGE` commands. The subsequent commands do perform
an action (e.g. creating a course, a module or a badge).

A very simple exsample file could look like this:

```
COURSE_COLUMNS;fullname;source_course_id
MODULE_COLUMNS;module;name;timeopen;timeclose;local_attendance_quiz_questionname;local_attendance_quiz_questiontext
BADGE_COLUMNS;criteriatype;grade
COURSE;Test attendance;10
MODULE;local_attendance_quiz;Day 1 Attendance;2026-01-06 10:30:00;2026-01-06 10:40:00;Attendance;I was here.
MODULE;local_attendance_quiz;Day 2 Attendance;2026-01-23 10:30:00;2026-01-23 10:40:00;Attendance;I was <strong>here</strong>.
BADGE;BADGE_CRITERIA_TYPE_COURSE;2
```

## New course

To create a new activity, a course is needed first. The use case assumes that you have a
course and want to add attendance tracking. This cannot be done in the same course,
but in parallel to the teaching course.
Therefore, a new course is created via the command `COURSE` in the import file. The `COURSE`
command accepts one of `source_course_id` for the course id, `source_couse_short` for
the short name of the course or `source_course_url` for the url of the course that
serves as the teaching course. The attendance course
is setup based on the teaching source course. However, some fields can be set in the
import file to change some aspects of the new course. See valid course columns above for
details.
The basic fields like start and endtime and category are taken from the source course
when not explicitly set in the import file. Be default, the new attendance course has
no students enroled. Enrolments can be copied from the source course by setting the
column `copyparticipants` to 1. A even more elegant solution is to enable the "Course
meta link" enrolment via setting the column `metaenrolment` to 1. However, for this
to work, the enrolment method must be enabled at your site.

The `USE_COURSE` command can be used instead if the `COURSE` command.
In this case, the defined existing course is used to create the activities in,
without creating a new course itself.

Valid course columns are:
* `name` {string} name of the course (can be empty, then a generic name will be used based
on the source course).
* `shortname` {string} short name of the course (can be empty, then a suffix will
be attached to the short name of the source course).
* `source_course_id` {int} id of the source course.
* `source_course_short` {string} short name of the source course.
* `source_course_url` {string} url to the source course.
* `category` {int} category id where the attendance course is created in. If empty
the category of the source course is used.
* `visible` {0|1}  if not provided use the same as the source course.
* `format` {string} if not provided use the same as the source course.
* `startdate` {int|datetime} timestamp or parsable date string, if not provided use
the same as the source course.
* `enddate` {int|datetime} timestamp or parsable date string, if not provided use
the same as the source course.
* `metaenrolment` {0|1} when set, the "Course meta link" enrolment method is added to
the course, and all participants from the source course will be autmatically synchronized
with this course. Note, this setting has no effect when this enrolment method is not
enabled in your Moodle site.
* `copyparticipants` {0|1} when set, the participants from the source course will be
enroled manually in the new course.
* `link_new_course` {string} link text that is inserted as module url in the "general"
section of the source course that links to the new course.

Note: one of `source_course_id`, `source_course_short`, or `source_course_url` must be
set.

## New module

Valid module columns are:
* `module` {string} technical name of the module, e.g. quiz, assign, page etc.
For the special use case of an attendance quiz use `local_attendance_quiz`. For
own implementations, you may use "frankenstyle_plugin_someclass". This will try
to load a class `\frankenstyle_plugin\mod\someclass` which must implement the interface
`\local_attencance\modcreate`.

* `section` {int} section identified number where to add the activity. If not set, 1 is used.
* `sectionid` {int} section identified by id where to add the activity. If not set `section` is used.
* `name` {string} name of the activity.
* `timeopen` {datetime} parsable date time string for the time when the activity opens.
* `timeclose` {datetime} parsable date time string for the closing time when the activity finishes.

Apart from these basic columns, there are many other activity specific columns that can be used
to setup the activity. For a quiz this would be:

* `quizpassword` {string} set a specific password to enter the quiz. If not set, a password
is created automatically (see below). If set but empty, then no access password is used.
* `attempts` {int} the number of attempts, default is 1.
* `timelimit` {int} the number of seconds how long the quiz might be answered, default is 60.

### Columns for attentance courses

This plugin was especially developed to create a course with quizzes to track the presence
of a student. The created module is always a quiz with the open and close time specified
in the columns. Also, a password is always set to access the quiz.
The attendance is confirmed by answering the only question that the quiz contains. Because
of the customization, a multichoice question with two options is created.

To control some of the aspects on how the attendance quiz is created, custom columns can
be used in the MODULE command. To distinguish these special fields, all of them start
with the prefix `local_attendance_quiz_`. Column names and their values can be
the following:

* `local_attendance_quiz_questionname` the name of the question created in the
quiz question bank. If not set, a language string is used.
* `local_attendance_quiz_questiontext` the question text that appears to the
students. If not set, a language string is used.
* `local_attendance_quiz_answer_yes` the response for the "yes" option (correct
answer to track attendance). If not set, the language string for "yes" is used.
* `local_attendance_quiz_answer_no` the response for the "no" option (incorrect
answer, however a multichoice question needs two answer options at least). If not set,
the language string for "no" is used.
* `local_attendance_quiz_feedback_yes` the feedback when "yes" was selected. If
not set, it remains empty.
* `local_attendance_attendancequiz_feedback_no` the feedback when "no" was selected. If
not set, it remains empty.
* `local_attendance_quiz_generalfeedback` the general feedback when the answer
was submitted. If not set, a language string is used.
* `local_attendance_quiz_passwordrule` the rule for randomly generated passwords.

### Quiz passwords

To generate random passwords (always with the length of 6 characters) the following values:
can be used:

* `lower` only lower case letters.
* `alpha` lower and upper case letters.
* `alnum` lower and upper case letters and digits.
* `all` lower and upper case letters, digits and special chars like `!@#$%&*()_+-={}[]|:;<>,.?/`.

Besides the automatic generation, a password can also be selected from a word list. At the
moment these rules are possible:

* `en` a noun from the english wordlist (945 entries)
* `de` a noun from the german wordlist (4279 entries)
* `color` a name of a color (50 entries)
* `nato` a name of the nato alphabeth (26 entries)
* `capital` a name of a capital city (240 entries)

All these lists are in lower case letters.

A password can also be set directly in the csv import file. In this case use the column name
`quizpassword`. That is an official field from the quiz module and therefore uses no prefix.
If you wish no password set for the quiz, use this field but do no set a value.

## Badge

Badges are awarded by completing a specific condition or when the trainer awards them manually.

For the use case of the attendance tracking we need a badge awarded to the student when
the quizzes in the attendance course were successfully taken. Therefore, the badge contains
a condition that the minimum grade is reached. In our case we have to presence dates where
the students get to answer a quiz with one question each that allows to get a credit of 1
point. To award a badge the students must be present on both days, answer the quiz correctly
and receive in total two points hence, meet the condition so that the badge is awarded to them.

The badge may have the following fields:

* `name` {string} name of the badge, when empty a default language string is used.
* `description` {string} description of the badge, when empty a default language string is used.
* `badgedisable` {any} if set, then the badge is not automatically enabled.

If a criteria is set for the badge, the following fields are needed:

* `criteriatype` {string|int} name or value of the criteria constants which are:
    * BADGE_CRITERIA_TYPE_ACTIVITY = 1
    * BADGE_CRITERIA_TYPE_MANUAL = 2
    * BADGE_CRITERIA_TYPE_SOCIAL = 3
    * BADGE_CRITERIA_TYPE_COURSE = 4
    * BADGE_CRITERIA_TYPE_COURSESET = 5
    * BADGE_CRITERIA_TYPE_PROFILE = 6
    * BADGE_CRITERIA_TYPE_BADGE = 7
    * BADGE_CRITERIA_TYPE_COHORT = 8
    * BADGE_CRITERIA_TYPE_COMPETENCY = 9

Depending on the criteria type, other fields must follow so that be badge criteria can be created
successfully. Otherwise, the badge might be broken. The constants can also use lower case letters.

Each badge needs an image. Images can be automatically created. This can be controlled via the
following fields:

* `imagefile` {string} file name when a separate file has been uploaded via the form and should
be used in the import.
* `imagecaption` {string} caption for the badge image. When not set, the course short name of the
previous source course or the just created course is used.
* `bgcolor` {string} hex annotation for the background color of the badge image (default: 2d89ef).
* `fgcolor` {string} hex annotation for the text color of the badge image (default: ffffff).
* `width` {int} image width, default 300.
* `height` {int} image height, default 300.
* `imagemode` {string|int} constants how to create the image:
    * TEXT_ONLY = 0: create a square with the course short name, use a GD Font.
    * TEXT_CHECKMARK = 1: create a square with a checkmark and the coure short name below using True Type Fonts.
    * TEXT_TTF = 2: create a square with the course short name, use a True Type Font.

The so created image is used for the badge. Images can be changed later on in Moodle.