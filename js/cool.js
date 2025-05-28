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

(function (window) {

  const iframeDefaultAttributes = {
    id: 'collabora-online-viewer',
    name: 'collabora-online-viewer',
    class: 'cool-frame__iframe',
    allow: 'clipboard-read *; clipboard-write *',
  };

  function createElement(tag, attributes) {
    const element = document.createElement(tag);
    for (const k in attributes) {
      element.setAttribute(k, attributes[k]);
    }
    return element;
  }

  function buildAndSubmitForm(action, payload, target) {
    const form = createElement('form', {
      action,
      enctype: 'multipart/form-data',
      method: 'post',
      target,
    });
    for (const name in payload) {
      const input = createElement('input', {
        type: 'hidden',
        name,
        value: payload[name],
      });
      form.append(input);
    }
    document.body.append(form);
    form.submit();
    form.remove();
  }

  function postToEditorFrame(iframe, id, values) {
    iframe.contentWindow.postMessage(JSON.stringify({
      MessageId: id,
      Values: values,
    }), '*');
  }

  function receiveMessage(iframe, closeButtonUrl, event) {
    const msg = JSON.parse(event.data);
    if (!msg) {
      return;
    }
    const postToEditor = postToEditorFrame.bind(null, iframe);

    switch (msg.MessageId) {
      case 'App_LoadingStatus':
        if (msg.Values && msg.Values.Status === 'Document_Loaded') {
          postToEditor('Host_PostmessageReady');
          postToEditor('Hide_Button', {id: 'renamedocument'});
        }
        break;

      case 'UI_Close':
        if (closeButtonUrl) {
          if (msg.Values && msg.Values.EverModified) {
            postToEditor('Action_Close');
          }
          if (window.parent.location === window.location) {
            // eslint-disable-next-line no-restricted-globals
            document.location.href = closeButtonUrl;
          }
          else {
            /* we send back the UI_Close message to the parent frame. */
            window.parent.postMessage(event.data);
          }
        }
        break;
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Only one editor per page/frame is supported, because the iframe has an
    // id attribute that would clash otherwise.
    const placeholder_element = document.querySelector('[data-collabora-online-editor]');
    if (!placeholder_element) {
      return;
    }
    const json = placeholder_element.getAttribute('data-collabora-online-editor');
    const data = JSON.parse(json);
    const iframe = createElement('iframe', {
      ...iframeDefaultAttributes,
      ...data.iframe_attributes,
    });
    const div = createElement('div', {class: 'cool-frame'});
    div.appendChild(iframe);
    placeholder_element.after(div);
    placeholder_element.remove();

    window.addEventListener('message', receiveMessage.bind(null, iframe, data.close_button_url));

    buildAndSubmitForm(data.action, data.payload, iframe.id);
  });

})(window);
