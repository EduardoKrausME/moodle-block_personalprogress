// This file is part of Moodle - http://moodle.org/

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

/**
 * Load the complete dashboard in one AJAX request.
 *
 * @param {Object} config
 */
export const init = (config) => {
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
