(function (wp, config) {
	if (!wp || !wp.richText || !wp.richText.registerFormatType || !wp.element || !wp.components || !wp.apiFetch) {
		return;
	}

	var __ = wp.i18n.__;
	var registerFormatType = wp.richText.registerFormatType;
	var getFormatType = wp.richText.getFormatType;
	var applyFormat = wp.richText.applyFormat;
	var RichTextToolbarButton = (wp.blockEditor && wp.blockEditor.RichTextToolbarButton) || (wp.editor && wp.editor.RichTextToolbarButton);
	var Popover = wp.components.Popover;
	var TextControl = wp.components.TextControl;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var apiFetch = wp.apiFetch;

	apiFetch.use(apiFetch.createNonceMiddleware(config.nonce));

	var formatName = 'gt-link-manager/link-inserter';

	if (!RichTextToolbarButton || !Popover || !TextControl || !Button) {
		return;
	}

	function LinkInserterEdit(props) {
		var value = props.value;
		var onChange = props.onChange;
		var isActive = props.isActive;
		var activeAttributes = props.activeAttributes || {};

		var _useState = useState(false);
		var isOpen = _useState[0];
		var setIsOpen = _useState[1];

		var _useState2 = useState('');
		var query = _useState2[0];
		var setQuery = _useState2[1];

		var _useState3 = useState([]);
		var results = _useState3[0];
		var setResults = _useState3[1];

		var _useState4 = useState(false);
		var loading = _useState4[0];
		var setLoading = _useState4[1];

		var _useState5 = useState('');
		var error = _useState5[0];
		var setError = _useState5[1];

		useEffect(function () {
			if (!isOpen) {
				return;
			}

			var cancelled = false;
			setLoading(true);
			setError('');

			apiFetch({
				path: config.restPath + '?search=' + encodeURIComponent(query) + '&per_page=20',
			})
				.then(function (items) {
					if (cancelled) {
						return;
					}
					setResults(Array.isArray(items) ? items : []);
				})
				.catch(function () {
					if (cancelled) {
						return;
					}
					setError(__('Could not load links.', 'gt-link-manager'));
					setResults([]);
				})
				.finally(function () {
					if (!cancelled) {
						setLoading(false);
					}
				});

			return function () {
				cancelled = true;
			};
		}, [query, isOpen]);

		function insertLink(item) {
			var rel = (item.rel || '').split(',').map(function (token) {
				return token.trim();
			}).filter(Boolean).join(' ');

			var nextValue = applyFormat(value, {
				type: 'core/link',
				attributes: {
					url: item.url,
					rel: rel,
				},
			});

			onChange(nextValue);
			setIsOpen(false);
		}

		return wp.element.createElement(
			Fragment,
			null,
			wp.element.createElement(RichTextToolbarButton, {
				icon: 'admin-links',
				title: __('GT Link', 'gt-link-manager'),
				onClick: function () {
					setIsOpen(!isOpen);
				},
				isActive: isActive || !!activeAttributes.url,
			}),
			isOpen &&
				wp.element.createElement(
					Popover,
					{
						onClose: function () {
							setIsOpen(false);
						},
						position: 'bottom center',
					},
					wp.element.createElement(TextControl, {
						label: __('Search GT Links', 'gt-link-manager'),
						value: query,
						onChange: setQuery,
						placeholder: __('Type a link name or slug', 'gt-link-manager'),
						autoFocus: true,
					}),
					loading && wp.element.createElement(Spinner, null),
					error &&
						wp.element.createElement(Notice, { status: 'error', isDismissible: false }, error),
					!loading &&
						!error &&
						results.map(function (item) {
							return wp.element.createElement(
								Button,
								{
									key: item.id,
									variant: 'secondary',
									onClick: function () {
										insertLink(item);
									},
									style: {
										display: 'block',
										width: '100%',
										marginBottom: '6px',
										textAlign: 'left',
									},
								},
								item.name + ' (' + item.slug + ')'
							);
						}),
					!loading && !error && !results.length &&
						wp.element.createElement('p', null, __('No links found.', 'gt-link-manager'))
				)
		);
	}

	function register() {
		if (typeof getFormatType === 'function' && getFormatType(formatName)) {
			return;
		}

		registerFormatType(formatName, {
			title: __('GT Link', 'gt-link-manager'),
			tagName: 'span',
			className: null,
			edit: LinkInserterEdit,
		});
	}

	if (typeof wp.domReady === 'function') {
		wp.domReady(register);
	} else {
		register();
	}
})(window.wp, window.gtLinkManagerEditor || {});
