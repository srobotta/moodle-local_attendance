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
(a so called shadow course) is required that only tracks the attendance
and awards the student a badge upon successful course completion (e.g.
when a certain threshold of presence items is reached). The attendance is
tracked by presenting the student a simple quiz with one question that
they must answer so that their presence is counted. The quiz is open for
the course presence time only and is password protected. The password is
revealed during the presence time. Some teachers reported that the password
for the test was given at the end of the presence time, to keep the students
attention until the end of the event.

This plugin offers an upload form so that managers can prepare an import
file with the course data to automatically create such courses. The plugin
includes also templates in OpenOffice format that should make it easier to
create the required input CSV file.

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
* `section_name_X` {string} section name (X is the section number) for a new section to
be generated in the course.
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

Note: one of `source_course_id`, `source_course_short`, or `source_course_url` must be
set.

### Link new course from source course

You may place the new course anywhere in your Moodle course structure. By default the
new course is created in the same category as the source course. For the attendance use case
we want a different category, because the attendance courses should not show up in the
existing course structure. Still the attendance course should be found from the main
course where attendance should be tracked. Therefore, we need a link from the main course
to the attendance course. This can be done via the following columns in the `COURSE_COLUMNS`:

* `link_new_course` {string} link text that is inserted as module URL in the first
section of the source course that links to the new course.
* `link_new_course_section` {int} section number, when the URL module should be inserted in
another existing section (counting starts at 1).
* `link_new_course_section_position` {int} the position inside the module, where the new
URL module should be inserted.

The latter two columns take effect only, when `link_new_course` is actually set. Otherwise,
they are ignored because no link in the source course will be created.

### Course completion

The new course may have completion criteria set. These columns are defined as well in the
`COURSE_COLUMNS`. In the `COURSE` commands these columns must be filled with values.

* `completion_criteria_overall_aggregation` {1|2|all|any} whether any or all of the given
completion crtieria must be fullfilled.
* `completion_criteria_activity` {string} a comma separated list of activity ids, that
must be completed.
* `completion_criteria_activity_aggregation` {1|2|all|any}, makes sense only when
`completion_criteria_activity` is set to complete any or all the activities.
* `completion_criteria_course` {string} a comma separated list of course ids, that
must be completed.
* `completion_criteria_course_aggregation` {1|2|all|any}, makes sense only when
`completion_criteria_course` is set to complete any or all the courses.
* `completion_criteria_role` {string} a comma separated list of role ids, that
may set the course to be completed
* `completion_criteria_role_aggregation` {1|2|all|any}, makes sense only when
`completion_criteria_role` is set to allow complete course by user of one specific roles or one from each role.
* `completion_criteria_date` {datetime} set date when the coruse is set to be completed.
* `completion_criteria_duration` {int} set a duration in days after user enrolment.
* `completion_criteria_grade` {float} set a grade that the user must achieve over all activities in the course.
* `completion_criteria_unenrol` {0|1} course is completed when user is unenroled from the course.
* `completion_criteria_self` {0|1} course can be set manually as completed by the user.

For Attendance courses, this is essential because the successful participation in a course
is controlled by the course completion. In our use case we define:

* `completion_criteria_grade` with a grade that must be reached in the course for all attendance
questions. Each question is rewarded with 1 point. If you have 10 presence dates and want to
have 80% of attendancy, then the grade must be 8.
* `completion_criteria_date` this is set with the date of (or better after) the last day of
presence. Even though students have the minimum threshold of necessary attendance dates fulfiled
already, the attendance badge should not be rewarded before the course is terminated.

### Course sections

When a course is created, by default there is one section "General" and there is usually a 
anouncement forum. The exact behaviour can be influenced via admin settings. This plugins
does not create other sections besides the default section. However, you may set section
names in the CSV and also control where modules are created.

In the course columns you define a new section by `section_name_X` where X is a number of
1 and higher. In the `COURSE` row you set the section name of that particular section.

This following example demonstrates it:
```
COURSE_COLUMNS;source_course_id;name;section_name_1;section_name_2;section_name_3
COURSE;1;My Atendance course;Obligated Modules;Voluntary Modules;Other
```

This would create a course with 3 sections with the names "Obligated Modules",
"Voluntary Modules" and "Other". You could even skip `section_name_2`. In this
case section 2 is still generated, because there is section 3 defined and there
can't be an undefined section. However, the section name is set with the default
value for section 2 because it is not explicit defined.

## New module

Valid module columns are:
* `module` {string} technical name of the module, e.g. quiz, assign, page etc.
For the special use case of an attendance quiz use `local_attendance_quiz`. For
own implementations, you may use "frankenstyle_plugin_someclass". This will try
to load a class `\frankenstyle_plugin\mod\someclass` which must implement the interface
`\local_attencance\modcreate`.

* `section` {int} section identified number where to add the activity. If not set, 1 is used.
* `sectionid` {int} section identified by id where to add the activity. If not set `section` is used.
* `section_pos` {int} position where the new module is placed inside the given section. By default,
the module is appended after the last existing module in that section.
* `name` {string} name of the activity.
* `timeopen` {datetime} parsable date time string for the time when the activity opens.
* `timeclose` {datetime} parsable date time string for the closing time when the activity finishes.

Apart from these basic columns, there are many other activity specific columns that can be used
to setup the activity. For a quiz this would be:

* `quizpassword` {string} set a specific password to enter the quiz. If not set, a password
is created automatically (see below). If set but empty, then no access password is used.
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

## OpenOffice templates

In the plugin directory the folder `office-templates` contains OpenOffice templates that can be used
for creating the course list and finally saving the file as CSV and then use it in the import.

### Attendance template

The `Attendance.odt` template contains basically our use case that was the motivation for creating this plugin.
The lines starting with `#` are there for documentation, will be contained when saving a CSV but
are ignored during the import.

#### Predefined fields

Row _2_ and _3_ hold some predefinded data, that is used in the document later on in other cells. The
fieldnames are in row _2_ as defined in the import, the actual values are in row _3_. In _C2_ the
field name `link_new_course` is the explanation for field _C3_. This value is used in all
`COURSE` rows in column _H_, with the field type defined in _H5_ (in the course columns). Likewise
the question text for all attendance quizzes is taken from _B3_ (used in _H_ for all module columns).

#### Course and dates

The course for which the attendance is required is defined
in cell _B8_. Adapt the link to your course. In case if you want to use the short course name,
change the cell _B5_ to `source_course_short` and enter the correct short name in _B8_.

The course name in _C8_ is not used for identifying the course. It is used as a reference for
the trainer to have a readable name of the course. However, the cell content is used in the
cell _D9_ where the badge description is defined.

The template is prefilled with some data that must be changed. Attendance dates are starting from
row 10 with the `MODULE` command and the value `local_attendance_quiz` in column _B_ where the
module technical name is defined.

The name of the quiz (Attendance for day X afternoon) that the student must visit to confirm
the attendance of that single event, is defined in column _C_ (field `name` of the module columns).
To have a nicer label, the start and end date from columns _D_ and _E_ are used and concatenated
in a formula. This content must be copied in a way, that each row refers to their corresponding
columns in the same row.

**Note**: If you use a German (or other language) Office program, the formula in the _C_ columns
must be adjusted to use the correct date fields. In German the field content may look like:

```
=CONCAT("Nachweis der Anwesenheit am ", TEXT(D10, "TT.MM.JJJJ"), " ",  IF(TEXT(D10, "HH") < "12", "vor", "nach"), "mittags.")
```

#### Completion criteria

Cells _F8_ and _F9_ are both for the completion criteria, e.g. how many times a student must
tick the attendance to receive the badge for completion. This number depends on the number of
dates below (in the template there are 17 from row 10 - 27) where a student may miss three
ocurrences but is still rewarded the badge.

#### Additional files

The badge image defined in _E8_ must be separately uploaded in the import form. The uploaded
image name must be the same as in the templated. If the image cannot be found, the import is
skiped for this course.

#### Participants

To make things easy, the template assumes that your Moodle has the "Course meta enrolment" enabled
so that students (ant teachers) are automatically enroled in the attendence course, when they
are enroled in the main course for which attendance is required. With this setting, attendance
courses can created in advance even not yet knowing who will actually be part of the real course.
The setting is controlled by the field _E5_ which has the value 1 in all course rows.

If you do not want that, either empty _E5_ or set in the _E_ columns of the course "0" or simply
remove the 1 (in the sample, this is cell _E8_).

If you just want to copy the enrolments, you may change _E5_ to the value `copyparticipants`.

### Attendance for groups

The `Attendance_Groups.odt` template is very similar to the other template. However, for this
use case the course that needs attendance tracking is visited by two (or more) groups. Each group
must pass a certain amount of presence dates on which attendance must be confirmed. Each group
of students has it's own dates.

To make this work, from the original course, we create two attendance courses to track attendance
of each group in a separate course. The template uses the same source course twice but with
different attentance dates in each shadow course.

In this template the shortname of the attendance courses must be set manually in the template
(using the field definition in _E5_ and the values in _E8_ and _E20_). The link text from the main
course to the attendance courses is also distinguished (field `link_new_course` in _H5_ with the
two values in _H8_ and _H20_). Because of the different groups no automatic enrolment is done,
hence the two attendence courses created will have no participants.

From the 10 given dates for each group in the attendance courses 8 need to be attended to get the
awarded badge.

Apart from these changes, the rest of the template is very similar to the general template.

After the import was done, in the original course, the students must be divided into two groups.
The group members of each group need to be enroled in one of the new attendance courses. The two
links from the main course to the attendance courses should get a access restriction set by group,
so that each group gets to see their course only.

### Troubleshooting during import

In case of an error, the import is stopped for the single course. This is the case, if there is a
problem with the `COURSE` line (e.g. source course not found) or in the subsequent process when
an activity throws an error.

The upload and import needs quite some memory for all it's operations. While memory is increased there
still might be the chance that you run into  a 500 error during the upload and creation process. In
this case, check manually which new courses have been created and delete these. Also delete
any links in the original course that may point to the new created attendance course that you just
deleted. Then split the file into smaller chunks and try again.

#### Deleting a course

If something goes wrong, you should consider to delete the new attendance course and start over again.
This is the easiest way of dealing with incomplete imports and course creations.

Course IDs can be easiliy checked in the log output. When there was a 500 error, the log does not
exist and it must be checked via the course management in the admin area or simply by looking in
the database course table and check for the highest ids e.g.

```
select id, shortname, fullname from course order by id desc limit 10;
```

Deleting a new course is easiest done on the cli (in case you have access to the server) by running:

```
sudo -u www-data php /path/to/your/moodle/admin/cli/delete_course.php -c=ID --disablerecyclebin
```

You may also use the course admin area but then have to navigate through the category structure to
find your course which makes the process a bit more complicated.