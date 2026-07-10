/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./admin/assets/src/restrict-block/index.js":
/*!**************************************************************!*\
  !*** ./admin/assets/src/restrict-block/index.js + 5 modules ***!
  \**************************************************************/
/***/ ((__unused_webpack_module, __unused_webpack___webpack_exports__, __webpack_require__) => {


;// ./admin/assets/src/restrict-block/style.scss
// extracted by mini-css-extract-plugin

;// external ["wp","element"]
const external_wp_element_namespaceObject = window["wp"]["element"];
;// external ["wp","components"]
const external_wp_components_namespaceObject = window["wp"]["components"];
;// external ["wp","i18n"]
const external_wp_i18n_namespaceObject = window["wp"]["i18n"];
;// external ["wp","apiFetch"]
const external_wp_apiFetch_namespaceObject = window["wp"]["apiFetch"];
var external_wp_apiFetch_default = /*#__PURE__*/__webpack_require__.n(external_wp_apiFetch_namespaceObject);
;// ./admin/assets/src/restrict-block/index.js
/**
 * Block Restriction Editor Script.
 *
 * Adds "Restrict This Block" panel to Gutenberg blocks.
 * Shows Pro upgrade banner when SureMembers Pro is not active.
 *
 * @package SureMembersCore
 * @since 1.0.0
 */







/**
 * Fetch access groups via AJAX.
 */
const fetchAccessGroups = async ({
  planIds,
  title,
  ajax_url,
  includeIds
}) => {
  const formData = new FormData();
  formData.append('action', 'suremembers_postmeta_search');
  formData.append('security', suremembers_global.suremembers_postmeta_security);
  if (planIds) {
    formData.append('selected_ids', planIds.map(item => item.id));
  }
  if (includeIds) {
    formData.append('include_ids', includeIds);
  }
  if (title && title !== '') {
    formData.append('search_title', title);
  }
  return await external_wp_apiFetch_default()({
    url: ajax_url,
    method: 'POST',
    body: formData
  }).then(response => response);
};

/**
 * Search input component.
 */
const SearchInput = props => {
  const {
    props: blockProps,
    planIds,
    setPlanIds,
    setPlanIdsHandler,
    setFocusOninput,
    showAccess,
    setShowAccess,
    inputRef,
    setShowAccessError,
    searchValue,
    setSearchValue
  } = props;
  const fetchGroups = (shouldMerge = true, extraArgs = false) => {
    const args = {
      planIds,
      title: searchValue,
      ajax_url: suremembers_global.ajax_url
    };
    if (extraArgs) {
      Object.assign(args, extraArgs);
    }
    const promise = fetchAccessGroups(args);
    promise.then(response => {
      if (!response.success || response.success === false) {
        setShowAccess(false);
        if (response.data.message) {
          setShowAccessError(response.data.message);
        }
        return;
      }
      if (shouldMerge) {
        setShowAccess([...response.data, ...planIds]);
        if (extraArgs.includeIds) {
          const filtered = response.data.filter(item => extraArgs.includeIds.includes(item.id));
          setPlanIds(filtered);
        }
      } else {
        setShowAccess(response.data);
      }
    });
  };
  (0,external_wp_element_namespaceObject.useEffect)(() => {
    if (searchValue !== null) {
      if (searchValue && searchValue.length > 1) {
        const timeout = setTimeout(() => {
          fetchGroups();
        }, 500);
        return () => {
          clearTimeout(timeout);
        };
      }
    } else {
      fetchGroups();
    }
  }, [searchValue]);
  (0,external_wp_element_namespaceObject.useEffect)(() => {
    if (blockProps.attributes.sureMemberRestrictions && blockProps.attributes.sureMemberRestrictions.length) {
      fetchGroups(true, {
        includeIds: blockProps.attributes.sureMemberRestrictions
      });
    } else {
      setShowAccess(showAccess);
    }
  }, []);
  return (0,external_wp_element_namespaceObject.createElement)('input', {
    onBlur: () => {
      setFocusOninput(false);
    },
    onfocusin: () => {
      setFocusOninput(true);
    },
    ref: inputRef,
    className: 'search-input',
    type: 'text',
    value: searchValue || '',
    onChange: e => {
      const {
        value
      } = e.target;
      setSearchValue(() => value === '' ? null : value);
    },
    onKeyDown: e => {
      if (e.keyCode === 8 && e.target.value === '' && planIds.length > 0) {
        const lastIndex = [...planIds].length - 1;
        setPlanIdsHandler('remove', lastIndex);
      }
    }
  });
};

/**
 * Selected plans display component.
 */
const SelectedPlans = props => {
  const {
    props: blockProps,
    planIds,
    setPlanIds,
    setPlanIdsHandler,
    setFocusOninput,
    inputRef,
    showAccess,
    setShowAccess,
    setShowAccessError,
    addRemoveFromAttr,
    searchValue,
    setSearchValue
  } = props;
  const searchInputElement = (0,external_wp_element_namespaceObject.createElement)(SearchInput, {
    props: blockProps,
    planIds,
    setPlanIds,
    setPlanIdsHandler,
    setFocusOninput,
    showAccess,
    setShowAccess,
    inputRef,
    setShowAccessError,
    searchValue,
    setSearchValue
  });
  const selectedItems = planIds.length >= 1 && planIds.map((item, index) => {
    const truncatedTitle = item.title.length > 18 ? item.title.substring(0, 18) + '...' : item.title;
    return (0,external_wp_element_namespaceObject.createElement)('span', {
      className: 'suremembers-group-item',
      key: index
    }, truncatedTitle, (0,external_wp_element_namespaceObject.createElement)('span', {
      onClick: () => {
        const itemIndex = [...planIds].findIndex(p => p.id === item.id);
        addRemoveFromAttr('remove', item.id);
        setPlanIdsHandler('remove', itemIndex);
      },
      className: 'dashicons dashicons-no-alt'
    }));
  });
  return (0,external_wp_element_namespaceObject.createElement)(external_wp_element_namespaceObject.Fragment, null, selectedItems, searchInputElement);
};

/**
 * Access groups dropdown component.
 */
const AccessGroupsDropdown = props => {
  const {
    planIds,
    setPlanIdsHandler,
    showAccess,
    showAccessError
  } = props;
  if (showAccess === false) {
    return (0,external_wp_element_namespaceObject.createElement)('div', {
      className: 'suremembers-post-meta-restricted-post-result'
    }, (0,external_wp_element_namespaceObject.createElement)('span', {
      className: 'suremembers-not-found-error'
    }, showAccessError));
  }
  const options = showAccess.length ? showAccess.map((item, index) => {
    if (planIds.findIndex(p => p.id === item.id) >= 0) {
      return null;
    }
    return (0,external_wp_element_namespaceObject.createElement)('div', {
      onClick: () => {
        const newPlan = {
          id: item.id,
          title: item.title
        };
        setPlanIdsHandler('add', newPlan);
      },
      key: index
    }, (0,external_wp_element_namespaceObject.createElement)('span', {
      className: 'suremembers-title'
    }, item.title));
  }) : null;
  return options && (0,external_wp_element_namespaceObject.createElement)('div', {
    className: 'suremembers-post-meta-restricted-post-result'
  }, options);
};

/**
 * Pro upgrade text component.
 */
const ProUpgradeBanner = () => {
  return (0,external_wp_element_namespaceObject.createElement)('p', {
    className: 'suremembers-pro-upgrade-text'
  }, (0,external_wp_i18n_namespaceObject.__)('Block-level restrictions are available in SureMembers Pro.', 'suremembers-core'), ' ', (0,external_wp_element_namespaceObject.createElement)(external_wp_components_namespaceObject.ExternalLink, {
    href: suremembers_global?.upgrade_url || 'https://suremembers.com/pricing/?utm_source=suremembers-core&utm_medium=block-editor&utm_campaign=upgrade'
  }, (0,external_wp_i18n_namespaceObject.__)('Upgrade to premium', 'suremembers-core')));
};

/**
 * Main restriction panel component.
 */
const RestrictionPanel = props => {
  const {
    sure_member_access_groups,
    sure_member_create_group,
    is_premium_active
  } = suremembers_global;
  const {
    attributes,
    setAttributes
  } = props;
  const accessGroups = sure_member_access_groups && sure_member_access_groups[0] ? sure_member_access_groups : [];
  const [planIds, setPlanIds] = (0,external_wp_element_namespaceObject.useState)([]);
  const [showAccess, setShowAccess] = (0,external_wp_element_namespaceObject.useState)(accessGroups);
  const [showAccessError, setShowAccessError] = (0,external_wp_element_namespaceObject.useState)((0,external_wp_i18n_namespaceObject.__)("Plan's are not available.", 'suremembers-core'));
  const [focusOninput, setFocusOninput] = (0,external_wp_element_namespaceObject.useState)(false);
  const [showDropdown, setShowDropdown] = (0,external_wp_element_namespaceObject.useState)(false);
  const [searchValue, setSearchValue] = (0,external_wp_element_namespaceObject.useState)('');
  const inputRef = (0,external_wp_element_namespaceObject.useRef)(null);
  const containerRef = (0,external_wp_element_namespaceObject.useRef)(null);
  const addRemoveFromAttr = (action, id) => {
    const currentRestrictions = attributes.sureMemberRestrictions ? [...attributes.sureMemberRestrictions] : [];
    if (action === 'add') {
      currentRestrictions.push(id);
    } else {
      const index = currentRestrictions.indexOf(id);
      currentRestrictions.splice(index, 1);
    }
    setAttributes({
      sureMemberRestrictions: currentRestrictions
    });
  };
  const setPlanIdsHandler = (action, value) => {
    if (action === 'add') {
      setPlanIds(prev => {
        const updated = [...prev];
        updated.push(value);
        return updated;
      });
      addRemoveFromAttr('add', value.id);
      setShowDropdown(false);
      setSearchValue(null);
    } else if (action === 'remove') {
      setPlanIds(prev => {
        const updated = [...prev];
        updated.splice(value, 1);
        return updated;
      });
    }
  };
  const classNames = (...classes) => {
    return classes.filter(Boolean).join(' ');
  };
  (0,external_wp_element_namespaceObject.useEffect)(() => {
    if (showDropdown) {
      const handleClickOutside = e => {
        if (containerRef.current && !containerRef.current.contains(e.target)) {
          setShowDropdown(false);
        }
      };
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [containerRef, showDropdown]);

  // Show Pro upgrade banner if premium is not active.
  if (!is_premium_active) {
    return (0,external_wp_element_namespaceObject.createElement)(external_wp_components_namespaceObject.PanelBody, {
      title: (0,external_wp_i18n_namespaceObject.__)('Restrict This Block', 'suremembers-core'),
      initialOpen: false,
      className: 'suremembers-restrict-block-panel'
    }, (0,external_wp_element_namespaceObject.createElement)(ProUpgradeBanner, null));
  }

  // Show create group link if no groups exist.
  if (sure_member_create_group && sure_member_create_group !== '') {
    return (0,external_wp_element_namespaceObject.createElement)(external_wp_components_namespaceObject.PanelBody, {
      title: (0,external_wp_i18n_namespaceObject.__)('Restrict This Block', 'suremembers-core'),
      initialOpen: false
    }, (0,external_wp_element_namespaceObject.createElement)('a', {
      className: 'suremembers-create-group',
      href: sure_member_create_group,
      target: '_blank',
      rel: 'noreferrer'
    }, (0,external_wp_i18n_namespaceObject.__)('Add Access Group', 'suremembers-core')));
  }

  // Show restriction controls if groups exist.
  if (sure_member_access_groups && sure_member_access_groups.length) {
    return (0,external_wp_element_namespaceObject.createElement)(external_wp_components_namespaceObject.PanelBody, {
      title: (0,external_wp_i18n_namespaceObject.__)('Restrict This Block', 'suremembers-core'),
      initialOpen: false
    }, (0,external_wp_element_namespaceObject.createElement)(external_wp_element_namespaceObject.Fragment, null, (0,external_wp_element_namespaceObject.createElement)('div', {
      className: 'suremembers-post-meta-choose',
      ref: containerRef
    }, (0,external_wp_element_namespaceObject.createElement)('div', {
      className: 'suremembers-post-meta-restrcited-content ' + (focusOninput ? 'suremembers-focuson' : '')
    }, (0,external_wp_element_namespaceObject.createElement)('div', {
      className: 'suremembers-post-meta-restrcited-post-provider'
    }, (0,external_wp_element_namespaceObject.createElement)('div', {
      className: 'components-base-control'
    }, (0,external_wp_element_namespaceObject.createElement)('label', {
      className: 'suremembers-label-head'
    }, (0,external_wp_i18n_namespaceObject.__)('Show block when user ', 'suremembers-core')), (0,external_wp_element_namespaceObject.createElement)(external_wp_components_namespaceObject.ButtonGroup, null, (0,external_wp_element_namespaceObject.createElement)(external_wp_components_namespaceObject.Button, {
      onClick: () => {
        setAttributes({
          sureMemberShowOnRestriction: 'is_in'
        });
      },
      className: classNames(attributes.sureMemberShowOnRestriction === 'is_in' ? 'is-primary' : 'is-secondary', 'components-button')
    }, (0,external_wp_i18n_namespaceObject.__)('Is In', 'suremembers-core')), (0,external_wp_element_namespaceObject.createElement)(external_wp_components_namespaceObject.Button, {
      onClick: () => {
        setAttributes({
          sureMemberShowOnRestriction: 'is_not_in'
        });
      },
      className: classNames(attributes.sureMemberShowOnRestriction === 'is_not_in' ? 'is-primary' : 'is-secondary', 'components-button')
    }, (0,external_wp_i18n_namespaceObject.__)('Is Not In', 'suremembers-core')))), (0,external_wp_element_namespaceObject.createElement)('label', {
      className: 'suremembers-label-head'
    }, (0,external_wp_i18n_namespaceObject.__)('Memberships', 'suremembers-core')), (0,external_wp_element_namespaceObject.createElement)('div', {
      className: 'suremembers-multiple-plans-container'
    }, (0,external_wp_element_namespaceObject.createElement)('div', {
      className: 'suremembers-input-added-group',
      onClick: () => {
        setFocusOninput(true);
        setShowDropdown(true);
        inputRef.current.focus();
      }
    }, (0,external_wp_element_namespaceObject.createElement)(SelectedPlans, {
      props,
      planIds,
      setPlanIds,
      setPlanIdsHandler,
      focusOninput,
      setFocusOninput,
      inputRef,
      showAccess,
      setShowAccess,
      setShowAccessError,
      addRemoveFromAttr,
      searchValue,
      setSearchValue
    })), showDropdown && (0,external_wp_element_namespaceObject.createElement)(AccessGroupsDropdown, {
      props,
      planIds,
      setPlanIdsHandler,
      showAccess,
      showAccessError
    })))))));
  }
  return '';
};

// Higher Order Component to add inspector controls.
const {
  createHigherOrderComponent
} = wp.compose;
const {
  InspectorControls
} = wp.blockEditor;
const withInspectorControl = createHigherOrderComponent(BlockEdit => {
  return props => {
    const {
      isSelected
    } = props;
    if (isSelected === true) {
      return (0,external_wp_element_namespaceObject.createElement)(external_wp_element_namespaceObject.Fragment, null, (0,external_wp_element_namespaceObject.createElement)(BlockEdit, props), (0,external_wp_element_namespaceObject.createElement)(InspectorControls, null, (0,external_wp_element_namespaceObject.createElement)(RestrictionPanel, props)));
    }
    return (0,external_wp_element_namespaceObject.createElement)(BlockEdit, props);
  };
}, 'withInspectorControl');

// Register block attributes.
wp.hooks.addFilter('blocks.registerBlockType', 'suremembers/with-inspector-controls', settings => {
  if (settings.attributes) {
    settings.attributes = {
      ...settings.attributes,
      sureMemberRestrictions: {
        type: 'array',
        default: []
      },
      sureMemberShowOnRestriction: {
        type: 'string',
        default: 'is_in'
      }
    };
  }
  return settings;
});

// Add inspector controls to all blocks.
wp.hooks.addFilter('editor.BlockEdit', 'suremembers/with-inspector-controls', withInspectorControl);

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
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
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"restrict_block": 0,
/******/ 			"./style-restrict_block": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunksuremembers_core"] = globalThis["webpackChunksuremembers_core"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["./style-restrict_block"], () => (__webpack_require__("./admin/assets/src/restrict-block/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=restrict_block.js.map