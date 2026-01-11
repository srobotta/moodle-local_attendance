## Generate attendance courses

The use case for this plugin is to have automatically set up a course
to check the student attendance by having them answer one question
in a test activity to check for the students precense in the course.

Unfortunately, the tracking cannot be done in the course directly that
is used for teaching, because the completion tracking depends on the
presence only and not of the assessements that a student must fulfill.
Therefore, to track the attendance of a course, a parallel Moodle course
is required that only tracks the presence and awards the studend a badge
upon successful course comletion (e.g. when a certain threshold of presence
items is tracked). This is done by presenting the students a simple
quiz with one question that they must answer successfully so that their
presence is counted. The quiz is open for the course presence time only
and is password protected.

The plugin offers an upload form so that managers can prepare an import
file with the course data to automatically create such courses.

### Import file format

The file format for creating one or more such presence check courses
with the presence dates is like a CSV but with some extra handling.

At the beginning of each line, the first column consists of a command
which can be:

* COURSE_COLUMNS
* MODULE_COLUMNS
* COURSE
* MODULE
* USE_COURSE

The first two commands `*_COLUMNS` define the field names of the following
`COURSE` and `MODULE` commands.

The `USE_COURSE` command can be used instead if the `COURSE` command.
In this case, the defined existing course is used to create the activities in,
without creating a new course itself.

Valid course columns are:
* `name` <string> name of the course (can be empty, then a generic name will be used based
on the source course).
* `shortname` <string> short name of the course (can be empty, then a suffix will
be attached to the short name of the source course).
* `source_course_id` <int> id of the source course, if not set then `source_course_short`
must be set.
* `source_course_short` <string> short name of the source course, if not set then
`source_course_id` must be set.
* `category` <int> category id where the attendance course is created in. If empty
the category of the source course is used.
* `visible` <0|1>  if not provided use the same as the source course.
* `format` <string> if not provided use the same as the source course.
* `startdate` <int|datetime> timestamp or parsable date string, if not provided use
the same as the source course.
* `enddate` <int|datetime> timestamp or parsable date string, if not provided use
the same as the source course.
* `nopaerticipants` <any> when set, the participants from the source course will not
be enroled in the new course.

Valid module columns are:
* `module` <string> technical name of the module, e.g. quiz, assign, page etc.
For the special use case of an attendance quiz use `local_attendance_quiz`. For
own implementations, you may use "frankenstyle_plugin_someclass". This will try
to load a class `\frankenstyle_plugin\mod\someclass` which must implement the interface
`\local_attencance\modcreate`.
* `section` <int> section identified number where to add the activity. If not set, 1 is used.
* `sectionid` <int> section identified by id where to add the activity. If not set `section` is used.
* `name` <string> name of the activity.
* `timeopen` <datetime> parsable date time string for the time when the test opens.
* `timeclose` <datetime> parsable date time string for the closing time when the test finishes.
* `quizpassword` <string> set a specific password to enter the quiz. If not set, a password
is created automatically (see below). If set but empty, then no access password is used.
* `attempts` <int> the number of attempts, default is 1.
* `timelimit` <int> the number of seconds how long the quiz might be answered, default is 60.

A sample file could look like this:

```
COURSE_COLUMNS;fullname;source_course_id
MODULE_COLUMNS;module;name;timeopen;timeclose
COURSE;Test attendance;23
MODULE;local_attendance_quiz;Day 1 Attendance;2026-01-06 10:30:00;2026-01-06 10:40:00
```

### New course

Because of the attendance course that must be created in parallel to the teaching course,
a new course is created via the command `COURSE` in the import file. The `COURSE`
command accepts one of `source_course_id` for the course id or `source_couse_short` for
the short name of the course that serves as the teaching course. The attendance course
is setup based on the teaching source course. However, some fields can be set in the
import file to change some aspects of the new course. See valid course columns above for
details.
The basic fields like start and endtime and category are taken from the source course
when not explicitly set in the import file. Also, all course participants from the source
course will be also enroled in the new course.

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
