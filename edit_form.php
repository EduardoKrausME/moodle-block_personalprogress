<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Instance configuration form.
 *
 * @package    block_personalprogress
 * @copyright  2026 Eduardo Kraus
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Block instance configuration.
 */
class block_personalprogress_edit_form extends block_edit_form {
    /**
     * Add instance fields.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform): void {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block_personalprogress'));

        $cards = [
            'level',
            'xp',
            'nextlevel',
            'weekly',
            'comparison',
            'streak',
            'goals',
            'quests',
            'milestones',
            'wallet',
            'course',
        ];

        foreach ($cards as $index => $card) {
            $showname = 'config_show' . $card;
            $mform->addElement(
                'advcheckbox',
                $showname,
                get_string('show' . $card, 'block_personalprogress')
            );
            $mform->setDefault($showname, 1);

            $ordername = 'config_order' . $card;
            $positions = array_combine(range(1, count($cards)), range(1, count($cards)));
            $mform->addElement(
                'select',
                $ordername,
                get_string('orderfor', 'block_personalprogress', get_string('card' . $card, 'block_personalprogress')),
                $positions
            );
            $mform->setDefault($ordername, $index + 1);
            $mform->hideIf($ordername, $showname, 'notchecked');
        }

        $mform->addElement(
            'advcheckbox',
            'config_showchart',
            get_string('showchart', 'block_personalprogress')
        );
        $mform->setDefault('config_showchart', 1);

        $mform->addElement(
            'advcheckbox',
            'config_showcoursetitle',
            get_string('showcoursetitle', 'block_personalprogress')
        );
        $mform->setDefault('config_showcoursetitle', 0);
    }
}
