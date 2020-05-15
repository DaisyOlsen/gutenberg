/**
 * External dependencies
 */
import { I18nManager } from 'react-native';
import 'react-native-get-random-values'; // This library works as a polyfill for the global crypto.getRandomValues which is needed by `uuid` version 7.0.0

/**
 * WordPress dependencies
 */
import { setLocaleData } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './globals';
import { getTranslation } from '../i18n-cache';
import initialHtml from './initial-html';
import setupApiFetch from './api-fetch-setup';

const reactNativeSetup  = () => {
	// Disable warnings as they disrupt the user experience in dev mode
	// eslint-disable-next-line no-console
	console.disableYellowBox = true;

	I18nManager.forceRTL( false ); // Change to `true` to debug RTL layout easily.
};

const gutenbergSetup = () => {
	const wpData = require( '@wordpress/data' );

	// wp-data
	const userId = 1;
	const storageKey = 'WP_DATA_USER_' + userId;
	wpData.use( wpData.plugins.persistence, { storageKey } );

	setupApiFetch();

	const isHermes = () => global.HermesInternal !== null;
	// eslint-disable-next-line no-console
	console.log( 'Hermes is: ' + isHermes() );

	setupInitHooks();

	const initializeEditor = require( '@wordpress/edit-post' ).initializeEditor;
	initializeEditor( {
		id: 'gutenberg',
		initialHtml,
		initialHtmlModeEnabled: false,
		initialTitle: 'Welcome to Gutenberg!',
		postType: 'post'
	} );
};

const setupInitHooks = () => {
	const wpHooks = require( '@wordpress/hooks' );

	wpHooks.doAction( 'native.setup-init-hooks' );

	wpHooks.addAction( 'native.render', 'core/react-native-editor', ( props ) => {
		setupLocale( props.locale, props.translations );
	} );

	// Map native props to Editor props
	wpHooks.addFilter(  'native.block_editor_props', 'core/react-native-editor', ( {
		initialData,
		initialTitle,
		initialHtmlModeEnabled,
		postType,
	} ) => ( {
		initialHtml: initialData,
		initialHtmlModeEnabled,
		initialTitle,
		postType,
	} ) );
};

const setupLocale = ( locale, extraTranslations ) => {
	const setLocaleData = require( '@wordpress/i18n' ).setLocaleData;

	I18nManager.forceRTL( false ); // Change to `true` to debug RTL layout easily.

	let gutenbergTranslations = getTranslation( locale );
	if ( locale && ! gutenbergTranslations ) {
		// Try stripping out the regional
		locale = locale.replace( /[-_][A-Za-z]+$/, '' );
		gutenbergTranslations = getTranslation( locale );
	}
	const translations = Object.assign(
		{},
		gutenbergTranslations,
		extraTranslations
	);
	// eslint-disable-next-line no-console
	console.log( 'locale', locale, translations );
	// Only change the locale if it's supported by gutenberg
	if ( gutenbergTranslations || extraTranslations ) {
		setLocaleData( translations );
	}
};

reactNativeSetup();
gutenbergSetup();
