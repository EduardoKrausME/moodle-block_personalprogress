// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

define([
    'core/ajax',
    'core/templates',
    'core/notification',
], function(Ajax, Templates, Notification) {
    'use strict';

    /**
     * Load the complete dashboard in one AJAX request.
     *
     * @param {Object} config
     */
    const init = (config) => {
        const root = document.getElementById(config.rootid);
        if (!root) {
            return;
        }

        const request = Ajax.call([{
            methodname: 'block_personalprogress_get_dashboard',
            args: {
                courseid: config.courseid,
                blockinstanceid: config.blockinstanceid,
            },
        }])[0];

        request
            .then((data) => Templates.render('block_personalprogress/dashboard', data))
            .then((html) => {
                root.innerHTML = html;
                root.setAttribute('aria-busy', 'false');
                return html;
            })
            .catch((error) => {
                root.textContent = root.dataset.errorLabel || '';
                root.setAttribute('aria-busy', 'false');
                Notification.exception(error);
            });
    };

    return {
        init,
    };
});
