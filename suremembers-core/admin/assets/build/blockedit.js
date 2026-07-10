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
/*!***************************************************************************!*\
  !*** ./admin/assets/src/centralized-restriction/BlockEdit.js + 7 modules ***!
  \***************************************************************************/

;// external ["wp","apiFetch"]
const external_wp_apiFetch_namespaceObject = window["wp"]["apiFetch"];
var external_wp_apiFetch_default = /*#__PURE__*/__webpack_require__.n(external_wp_apiFetch_namespaceObject);
;// external ["wp","components"]
const external_wp_components_namespaceObject = window["wp"]["components"];
;// external ["wp","data"]
const external_wp_data_namespaceObject = window["wp"]["data"];
;// external ["wp","element"]
const external_wp_element_namespaceObject = window["wp"]["element"];
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
;// ./admin/assets/src/centralized-restriction/BlockEdit.js







const EditApp = () => {
  const {
    nonce,
    all_access_url,
    new_access_url,
    ajax_url
  } = suremembers_edit;
  const [isModalOpen, setModalOpen] = (0,external_wp_element_namespaceObject.useState)(false);
  const [link, setLink] = (0,external_wp_element_namespaceObject.useState)('');
  const [accessGroups, setAccessGroups] = (0,external_wp_element_namespaceObject.useState)([]);
  const [isDropdownOpen, setDropdownOpen] = (0,external_wp_element_namespaceObject.useState)(false);

  // Content is protected when at least one active access group restricts it.
  // Show the colorful icon when restricted, grayscale otherwise.
  const isRestricted = accessGroups.length > 0;
  const LogoIcon = /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsxs)("svg", {
    width: "28",
    height: "28",
    viewBox: "0 0 28 28",
    fill: "none",
    xmlns: "http://www.w3.org/2000/svg",
    style: isRestricted ? undefined : {
      filter: 'grayscale(100%)'
    },
    children: [/*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsxs)("g", {
      clipPath: "url(#clip0_854_8589)",
      children: [/*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)("rect", {
        width: "28",
        height: "28",
        rx: "9.625",
        fill: "#5E25F8"
      }), /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)("path", {
        d: "M8.75391 5.25C11.17 5.25021 13.1289 7.20888 13.1289 9.625V22.75H13.1279V22.6621C11.1304 22.2572 9.62695 20.492 9.62695 18.375V8.75H7.87695V18.375C7.87695 20.4904 6.37423 22.2535 4.37891 22.6602V9.625C4.37891 7.20875 6.33766 5.25 8.75391 5.25Z",
        fill: "white"
      }), /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)("path", {
        d: "M14.0039 22.75C16.42 22.7498 18.3789 20.7911 18.3789 18.375V5.25H18.3779V5.33789C16.3804 5.74277 14.877 7.50797 14.877 9.625V19.25H13.127V9.625C13.127 7.50963 11.6242 5.74653 9.62891 5.33984V18.375C9.62891 20.7912 11.5877 22.75 14.0039 22.75Z",
        fill: "white"
      }), /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)("path", {
        d: "M28.8789 5.25H28.8779V19.25H23.6279V9.625C23.6279 7.5091 22.1249 5.74498 20.1289 5.33887V18.375C20.1289 20.7912 22.0877 22.75 24.5039 22.75H28.8789V5.25Z",
        fill: "white"
      }), /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)("path", {
        d: "M3.50391 22.75C5.91997 22.7498 7.87891 20.7911 7.87891 18.375V5.25H7.87793V5.33789C5.88082 5.74314 4.37793 7.5083 4.37793 9.625V19.25H-0.871094V22.75H3.50391Z",
        fill: "white"
      }), /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)("path", {
        d: "M19.2539 5.25C21.67 5.25021 23.6289 7.20888 23.6289 9.625V22.75H23.6279V22.6621C21.6304 22.2572 20.127 20.492 20.127 18.375V8.75H18.377V18.375C18.377 20.4904 16.8742 22.2535 14.8789 22.6602V9.625C14.8789 7.20875 16.8377 5.25 19.2539 5.25Z",
        fill: "white"
      })]
    }), /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)("defs", {
      children: /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)("clipPath", {
        id: "clip0_854_8589",
        children: /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)("rect", {
          width: "28",
          height: "28",
          rx: "9.625",
          fill: "white"
        })
      })
    })]
  });
  const postID = (0,external_wp_data_namespaceObject.useSelect)(select => select('core/editor').getCurrentPostId(), []);
  const postType = (0,external_wp_data_namespaceObject.useSelect)(select => select('core/editor').getCurrentPostType(), []);
  const isSavingPost = (0,external_wp_data_namespaceObject.useSelect)(select => select('core/editor').isSavingPost(), []);
  const PostTypelabel = (0,external_wp_data_namespaceObject.useSelect)(select => select('core/editor').getPostTypeLabel(), []);
  const updatedAccessGroups = async () => {
    const formData = new FormData();
    formData.append('action', 'suremembers_edit_get_active_access_groups');
    formData.append('security', nonce);
    formData.append('post_id', postID);
    formData.append('current_post_type', postType);
    return await external_wp_apiFetch_default()({
      url: ajax_url,
      method: 'POST',
      body: formData
    }).then(response => {
      if (response.success) {
        setAccessGroups(response.data.data);
      }
    });
  };
  (0,external_wp_element_namespaceObject.useEffect)(() => {
    if (null !== postID) {
      updatedAccessGroups();
    }
  }, [postID, postType, isSavingPost]);
  const handleAccessGroupClick = accessHref => {
    // For existing access groups - open the specific access group edit page
    const accessGroupUrl = accessHref + '&suremembers_view=iframe&suremembers_compact=1';
    setLink(accessGroupUrl);
    setDropdownOpen(false);
    setModalOpen(true);
  };
  const handleAllAccessGroupsClick = () => {
    // For "All Memberships" - open the main memberships listing page

    setLink(all_access_url + '&suremembers_view=iframe&suremembers_compact=1');
    setDropdownOpen(false);
    setModalOpen(true);
  };
  const handleNewAccessGroupClick = () => {
    // For "New Membership" - open the add new membership page

    setLink(new_access_url + '&suremembers_view=iframe&suremembers_compact=1');
    setDropdownOpen(false);
    setModalOpen(true);
  };
  return /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsxs)(external_ReactJSXRuntime_namespaceObject.Fragment, {
    children: [isModalOpen && /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(ModalComponent, {
      title: (0,external_wp_i18n_namespaceObject.sprintf)(
      // translators: %1$s Type of content.
      (0,external_wp_i18n_namespaceObject.__)('Restrict this %1$s', 'suremembers'), PostTypelabel),
      link: link,
      onRequestClose: () => {
        setModalOpen(false);
        updatedAccessGroups();
      }
    }), /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(external_wp_components_namespaceObject.DropdownMenu, {
      icon: LogoIcon,
      label: (0,external_wp_i18n_namespaceObject.__)('SureMembers Memberships', 'suremembers'),
      open: isDropdownOpen,
      onToggle: willOpen => setDropdownOpen(willOpen),
      onClose: () => setDropdownOpen(false),
      children: () => /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsxs)(external_wp_element_namespaceObject.Fragment, {
        children: [/*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(external_wp_components_namespaceObject.MenuGroup, {
          children: accessGroups.length > 0 ? accessGroups.map((access, key) => {
            return /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(external_wp_components_namespaceObject.MenuItem, {
              onClick: () => handleAccessGroupClick(access.href),
              children: access.title
            }, key);
          }) : /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsxs)(external_wp_components_namespaceObject.MenuItem, {
            children: [(0,external_wp_i18n_namespaceObject.sprintf)(
            // translators: %1$s Type of content.
            (0,external_wp_i18n_namespaceObject.__)('%1$s is not restricted', 'suremembers'), PostTypelabel), ' ']
          })
        }), /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsxs)(external_wp_components_namespaceObject.MenuGroup, {
          children: [/*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(external_wp_components_namespaceObject.MenuItem, {
            onClick: handleAllAccessGroupsClick,
            children: (0,external_wp_i18n_namespaceObject.__)('All Memberships', 'suremembers')
          }), /*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(external_wp_components_namespaceObject.MenuItem, {
            onClick: handleNewAccessGroupClick,
            children: (0,external_wp_i18n_namespaceObject.__)('New Membership', 'suremembers')
          })]
        })]
      })
    })]
  });
};

// Rest of your code remains the same...
(function (window, wp) {
  const rootDiv = document.createElement('div');
  rootDiv.classList.add('suremembers-edit-root');

  // check if gutenberg's editor root element is present.
  const editorEl = document.getElementById('editor');
  if (!editorEl) {
    // do nothing if there's no gutenberg root element on page.
    return;
  }
  const unsubscribe = wp.data.subscribe(function () {
    setTimeout(function () {
      (0,external_wp_element_namespaceObject.render)(/*#__PURE__*/(0,external_ReactJSXRuntime_namespaceObject.jsx)(EditApp, {}), rootDiv);
      if (!document.querySelector('.suremembers-edit-root')) {
        const toolbalEl = editorEl.querySelector('.edit-post-header-toolbar');
        if (toolbalEl instanceof HTMLElement) {
          toolbalEl.appendChild(rootDiv);
        }
      }
    }, 1);
  });
  // unsubscribe
  if (document.querySelector('.suremembers-edit-root')) {
    unsubscribe();
  }
})(window, wp);
/******/ })()
;
//# sourceMappingURL=blockedit.js.map