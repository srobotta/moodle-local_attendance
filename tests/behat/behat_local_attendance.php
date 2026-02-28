<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once(__DIR__ . '/../../../../repository/upload/tests/behat/behat_repository_upload.php');

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\When;

/**
 * File upload in filemanager element with scroll definition.
 *
 * The same as the normal upload, instead it has a js scroll into
 * view so that on the upload form, the second upload works as well.
 * With the standard step, the upload button was out of view.
 *
 * @package   local_attendance
 * @category  test
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_attendance extends behat_repository_upload {
    /**
     * Uploads a file to the specified filemanager leaving other fields in upload form default. The paths should be relative to moodle codebase.
     *
     * @When /^I upload "(?P<filepath_string>(?:[^"]|\\")*)" file to "(?P<filemanager_field_string>(?:[^"]|\\")*)" filemanager and scroll$/
     * @throws DriverException
     * @throws ExpectationException Thrown by behat_base::find
     * @param string $filepath
     * @param string $filemanagerelement
     */
    public function upload_file_to_filemanager_and_scroll($filepath, $filemanagerelement) {
        global $CFG;

        if (!$this->running_javascript()) {
            throw new DriverException(
                'Upload form with scroll is disabled in scenarios without Javascript support',
            );
        }

        $data = new TableNode([]);

        if (!$this->has_tag('_file_upload')) {
            throw new DriverException('File upload tests must have the @_file_upload tag on either the scenario or feature.');
        }

        $filemanagernode = $this->get_filepicker_node($filemanagerelement);

        // Opening the select repository window and selecting the upload repository.
        $this->open_add_file_window($filemanagernode, get_string('pluginname', 'repository_upload'));

        // Ensure all the form is ready.
        $noformexception = new ExpectationException('The upload file form is not ready', $this->getSession());
        $form = $this->find(
                'xpath',
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' container ')]" .
                "[contains(concat(' ', normalize-space(@class), ' '), ' repository_upload ')]" .
                "/descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' file-picker ')]" .
                "/descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' fp-content ')]" .
                "/descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' fp-upload-form ')]" .
                "/descendant::form",
                $noformexception
        );
        // Form elements to interact with.
        $file = $this->find_file('repo_upload_file');

        // Attaching specified file to the node.
        // Replace 'admin/' if it is in start of path with $CFG->admin .
        if (substr($filepath, 0, 6) === 'admin/') {
            $filepath = $CFG->dirroot . DIRECTORY_SEPARATOR . $CFG->admin .
                    DIRECTORY_SEPARATOR . substr($filepath, 6);
        }
        $filepath = str_replace('/', DIRECTORY_SEPARATOR, $filepath);
        if (!is_readable($filepath)) {
            $filepath = $CFG->dirroot . DIRECTORY_SEPARATOR . $filepath;
            if (!is_readable($filepath)) {
                throw new ExpectationException('The file to be uploaded does not exist.', $this->getSession());
            }
        }
        $file->attachFile($filepath);

        // Fill the form in Upload window.
        $datahash = $data->getRowsHash();

        // The action depends on the field type.
        foreach ($datahash as $locator => $value) {

            $field = behat_field_manager::get_form_field_from_label($locator, $this);

            // Delegates to the field class.
            $field->set_value($value);
        }

        // Submit the file.
        $submit = $this->find_button(get_string('upload', 'repository'));
        $submit->press();

        // We wait for all the JS to finish as it is performing an action.
        $this->getSession()->wait(self::get_timeout(), self::PAGE_READY_JS);

    }

    /**
     * Opens the filepicker modal window and selects the repository.
     *
     * @throws ExpectationException Thrown by behat_base::find
     * @param NodeElement $filemanagernode The filemanager or filepicker form element DOM node.
     * @param mixed $repositoryname The repo name.
     * @return void
     */
    protected function open_add_file_window($filemanagernode, $repositoryname) {
        $exception = new ExpectationException('No files can be added to the specified filemanager', $this->getSession());

        // We should deal with single-file and multiple-file filemanagers,
        // catching the exception thrown by behat_base::find() in case is not multiple
        $this->execute('behat_general::i_click_on_in_the', [
            'div.fp-btn-add a, input.fp-btn-choose', 'css_element',
            $filemanagernode, 'NodeElement'
        ]);

        // Wait for the default repository (if any) to load. This checks that
        // the relevant div exists and that it does not include the loading image.
        $this->ensure_element_exists(
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' file-picker ')]" .
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' fp-content ')]" .
                "[not(descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' fp-content-loading ')])]",
                'xpath_element');

        // Getting the repository link and opening it.
        $repoexception = new ExpectationException('The "' . $repositoryname . '" repository has not been found', $this->getSession());

        // Avoid problems with both double and single quotes in the same string.
        $repositoryname = behat_context_helper::escape($repositoryname);

        // Find matching repository links in all existing file-picker modal dialogs.
        $repositorylinks = $this->find_all(
            'xpath',
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' moodle-dialogue-base ')]" .
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' file-picker ')]" .
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' fp-repo-area ')]" .
                "//descendant::span[contains(concat(' ', normalize-space(@class), ' '), ' fp-repo-name ')]" .
                "[normalize-space(.)=$repositoryname]",
            $repoexception
        );
        $repositorylink = reset($repositorylinks);
        foreach ($repositorylinks as $repositorylink) {
            if ($repositorylink->isVisible()) {
                break;
            }
        }

        if (!$repositorylink->getParent()->getParent()->hasClass('active')) {
            // If the repository link is active, then the repository is already loaded.
            // Clicking it while it's active causes issues, so only click it when it isn't (see MDL-51014).
            $this->js_trigger_click($repositorylink);
        }
    }

}