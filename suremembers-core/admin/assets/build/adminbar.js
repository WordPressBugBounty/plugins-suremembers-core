/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/************************************************************************/
/*!**************************************************************************!*\
  !*** ./admin/assets/src/centralized-restriction/Adminbar.js + 7 modules ***!
  \**************************************************************************/

;// external ["wp","element"]
const external_wp_element_namespaceObject = window["wp"]["element"];
;// external ["wp","components"]
const external_wp_components_namespaceObject = window["wp"]["components"];
;// external ["wp","i18n"]
const external_wp_i18n_namespaceObject = window["wp"]["i18n"];
;// external "ReactJSXRuntime"
const external_ReactJSXRuntime_namespaceObject = window["ReactJSXRuntime"];
;// ./admin/assets/src/centralized-restriction/ModalComponent.js




/* harmony default export */ const ModalComponent = (({
  link,
  title,
  onRequestClose
}) => {
  (0,external_wp_element_namespaceObject.useEffect)(() => {
    // Add class to body when modal opens
    document.body.classList.add('suremembers-modal-open');
    console.log('Modal opened - body class added:', document.body.classList.contains('suremembers-modal-open'));

    // Inject CSS directly to force hide WordPress admin elements
    const style = document.createElement('style');
    style.id = 'suremembers-modal-hide-css';
    style.innerHTML = `
			#wpadminbar { display: none !important; visibility: hidden !important; height: 0 !important; }
			#adminmenumain, #adminmenuwrap, #adminmenu, #adminmenu-back { display: none !important; visibility: hidden !important; width: 0 !important; height: 0 !important; }
			#wpcontent { margin-left: 0 !important; padding-left: 0 !important; width: 100% !important; }
			#wpbody { margin-left: 0 !important; padding-left: 0 !important; }
		`;
    document.head.appendChild(style);

    // Remove class and CSS when modal closes
    return () => {
      document.body.classList.remove('suremembers-modal-open');
      const existingStyle = document.getElementById('suremembers-modal-hide-css');
      if (existingStyle) {
        existingStyle.remove();
      }
      console.log('Modal closed - body class removed and CSS cleaned up');
    };
  }, []);
  return /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(external_wp_components_namespaceObject.Modal, {
    className: "suremembers-modal",
    shouldCloseOnClickOutside: false,
    title: title,
    isFullScreen: false,
    onRequestClose: onRequestClose,
    children: /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)("iframe", {
      title: (0,external_wp_i18n_namespaceObject.__)('Access Settings', 'suremembers'),
      id: "sureMembersbariFrame",
      width: "100%",
      height: "600px",
      src: link,
      frameBorder: "0",
      allowFullScreen: true
    })
  });
});
;// external ["wp","apiFetch"]
const external_wp_apiFetch_namespaceObject = window["wp"]["apiFetch"];
var external_wp_apiFetch_default = /*#__PURE__*/__webpack_require__.n(external_wp_apiFetch_namespaceObject);
;// ./admin/assets/src/centralized-restriction/Adminbar.scss
// extracted by mini-css-extract-plugin

;// ./admin/assets/src/centralized-restriction/Adminbar.js





const PopupTrigger = () => {
  const [isModalOpen, setModalOpen] = (0,external_wp_element_namespaceObject.useState)(false);
  const [link, setLink] = (0,external_wp_element_namespaceObject.useState)('');
  const updateAdminBarList = () => {
    const formData = new FormData();
    formData.append('action', 'suremembers_fetch_adminbar_groups');
    formData.append('security', suremembers_adminbar.nonce);
    if ('undefined' !== typeof suremembers_adminbar.current_post_id) {
      formData.append('current_post_id', suremembers_adminbar.current_post_id);
    }
    formData.append('current_page_type', suremembers_adminbar.current_page_type);
    external_wp_apiFetch_default()({
      url: suremembers_adminbar.ajax_url,
      method: 'POST',
      body: formData
    }).then(response => {
      if (response.success === true) {
        if (response.data !== '') {
          const rootSelector = document.getElementById('wp-admin-bar-suremembers-admin-menu-bar-levels');
          const mainWrapper = document.getElementById('wp-admin-bar-suremembers-admin-menu-bar');
          if (null !== rootSelector) {
            if (0 !== response.data.access_groups.length) {
              mainWrapper.classList.add('suremembers-has-restrictions');
              const template = wp.template('suremembers-admin-bar-access-list');
              rootSelector.innerHTML = template({
                access_groups: response.data.access_groups
              });
            } else {
              rootSelector.innerHTML = `<li id="wp-admin-bar-suremembers-admin-menu-bar-levels-no_levels"><div class="ab-item ab-empty-item">Page is Not Restricted</div></li>`;
              mainWrapper.classList.remove('suremembers-has-restrictions');
            }
            bindClicks();
          }
        }
      }
    });
  };
  const bindClicks = () => {
    const sureMembersAdminBarLinks = document.querySelectorAll('.suremembers_adbar_itm');
    for (let i = 0; i < sureMembersAdminBarLinks.length; i++) {
      const linkElement = sureMembersAdminBarLinks[i].firstElementChild;
      linkElement.addEventListener('click', e => {
        e.preventDefault();
        const childLink = sureMembersAdminBarLinks[i].firstElementChild.getAttribute('href');
        if (childLink) {
          const linkWithParam = childLink + '&suremembers_view=iframe';
          setLink(linkWithParam);
          setModalOpen(true);
        }
      });
    }
  };
  (0,external_wp_element_namespaceObject.useEffect)(() => {
    bindClicks();
  }, null);
  return /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(external_ReactJSXRuntime_namespaceObject.Fragment, {
    children: isModalOpen && /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(ModalComponent, {
      title: suremembers_adminbar.modal_title,
      link: link,
      onRequestClose: () => {
        setModalOpen(false);
        updateAdminBarList();
      }
    })
  });
};
(function () {
  const app = document.getElementById('wp-admin-bar-suremembers-admin-menu-bar-suremembers_admin_bar_menu_hldr');
  document.addEventListener('DOMContentLoaded', function () {
    if (null !== app) {
      const root = (0,external_wp_element_namespaceObject.createRoot)(app);
      root.render(/*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(PopupTrigger, {}));
    }
  });
})();
/******/ })()
;
//# sourceMappingURL=adminbar.js.map