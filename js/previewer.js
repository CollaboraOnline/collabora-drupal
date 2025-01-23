/* -*- js-indent-level: 4 -*- */
/*
 * Copyright the Collabora Online contributors.
 *
 * SPDX-License-Identifier: MPL-2.0
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

function previewField(coolUrl) {
  const iframe = document.querySelector(
    '#cool-editor__dialog > .cool-frame__preview',
  );
  iframe.src = coolUrl;
  document.querySelector('#cool-editor__dialog').show();
}

function closePreview() {
  const iframe = document.querySelector(
    '#cool-editor__dialog > .cool-frame__preview',
  );
  iframe.src = 'about:blank';
  document.querySelector('#cool-editor__dialog').close();
}

function postReady() {
  postMessage({ MessageId: 'Host_PostmessageReady' });
}

(function () {
  function receiveMessage(event) {
    const msg = JSON.parse(event.data);
    if (!msg) {
      return;
    }

    switch (msg.MessageId) {
      case 'App_LoadingStatus':
        if (msg.Values && msg.Values.Status === 'Document_Loaded') {
          postReady();
        }
        break;
      case 'UI_Close':
        closePreview();
        break;
    }
  }

  window.addEventListener('message', receiveMessage, false);
})();
