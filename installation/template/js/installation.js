/**
 * @package		Joomla.Installation
 * @subpackage	JavaScript
 * @copyright	Copyright (C) 2005 - 2012 Open Source Matters. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

var Installation = new Class({
    initialize: function(container, base) {
        this.sampleDataLoaded = false;
        this.busy = false;
        this.container = container;
        this.spinner = new Spinner(this.container);
        this.baseUrl = base;
        this.view = '';

        this.pageInit();
    },

    pageInit: function() {
    	this.addToggler();
		// Attach the validator
		$$('form.form-validate').each(function(form){ this.attachToForm(form); }, document.formvalidator);
    },

	submitform: function() {
		var form = document.id('adminForm');

		if (this.busy) {
			alert(Joomla.JText._('INSTL_PROCESS_BUSY', 'Process is in progress. Please wait...'));
			return false;
		}

		if (form.task.value == 'setup.site') {
			$('setlanguage').value = $$('html').getProperty('lang')[0];
		}

		var req = new Request.JSON({
			method: 'post',
			url: this.baseUrl,
			onRequest: function() {
				this.spinner.show(true);
				this.busy = true;
				Joomla.removeMessages();
			}.bind(this),
			onSuccess: function(r) {
				Joomla.replaceTokens(r.token);
				if (r.messages) {
					Joomla.renderMessages(r.messages);
				}
				var lang = $$('html').getProperty('lang')[0];
				if (r.lang !== null && lang.toLowerCase() === r.lang.toLowerCase()) {
					Install.goToPage(r.data.view, true);
				} else {
					window.location = this.baseUrl+'?view='+r.data.view;
				}
			}.bind(this),
			onFailure: function(xhr) {
				this.spinner.hide(true);
				this.busy = false;
				var r = JSON.decode(xhr.responseText);
				if (r) {
					Joomla.replaceTokens(r.token);
					alert(r.message);
				}
			}.bind(this)
		});
		req.post(form.toQueryString()+'&task='+form.task.value+'&format=json');

		return false;
	},

	setlanguage: function() {
		var form = document.id('languageForm');

		if (this.busy) {
			alert(Joomla.JText._('INSTL_PROCESS_BUSY', 'Process is in progress. Please wait...'));
			return false;
		}

		var req = new Request.JSON({
			method: 'post',
			url: this.baseUrl,
			onRequest: function() {
				this.spinner.show(true);
				this.busy = true;
				Joomla.removeMessages();
			}.bind(this),
			onSuccess: function(r) {
				Joomla.replaceTokens(r.token);
				if (r.messages) {
					Joomla.renderMessages(r.messages);
				}
				var lang = $$('html').getProperty('lang')[0];
				if (lang.toLowerCase() === r.lang.toLowerCase()) {
					Install.goToPage(r.data.view, true);
				} else {
					window.location = this.baseUrl+'?view='+r.data.view;
				}
			}.bind(this),
			onFailure: function(xhr) {
				this.spinner.hide(true);
				this.busy = false;
				var r = JSON.decode(xhr.responseText);
				if (r) {
					Joomla.replaceTokens(r.token);
					alert(r.message);
				}
			}.bind(this)
		});
		req.post(form.toQueryString()+'&task=setup.setlanguage&format=json');

		return false;
	},

	goToPage: function(page, fromSubmit) {
		var url = this.baseUrl+'?tmpl=body&view='+page;
		var req = new Request.HTML({
			method: 'get',
			url: url,
			onRequest: function() {
				if (!fromSubmit) {
					Joomla.removeMessages();
					this.spinner.show(true);
				}
			}.bind(this),
			onSuccess: function (r) {
				this.view = page;
				document.id(this.container).empty().adopt(r);

				// Attach JS behaviors to the newly loaded HTML
				this.pageInit();

				this.spinner.hide(true);
				this.busy = false;

				initElements();
			}.bind(this)
		}).send();

		return false;
	},


	install: function(tasks, step_width) {
		var progress = document.id('install_progress').getElement('div.bar');

		if (!tasks.length) {
			progress.setStyle('width',(progress.getStyle('width').toFloat()+(step_width*3))+'%');
			this.goToPage('complete');
		}

		if (!step_width) {
			var step_width = (100 / tasks.length) / 11;
		}

		var task = tasks.shift();
		var form = document.id('adminForm');
		var tr = document.id('install_'+task);
		var spinner = tr.getElement('div.spinner');

		var req = new Request.JSON({
			method: 'post',
			url: 'index.php?'+form.toQueryString(),
			data: {'task':'setup.install_'+task, 'format':'json'},
			onRequest: function() {
				progress.setStyle('width',(progress.getStyle('width').toFloat()+step_width)+'%');
				tr.addClass('active');
				spinner.setStyle('visibility','visible');
			},
			onSuccess: function(r) {
				Joomla.replaceTokens(r.token);
				if (r.messages) {
					Joomla.renderMessages(r.messages);
					Install.goToPage(r.data.view, true);
				} else {
					progress.setStyle('width',(progress.getStyle('width').toFloat()+(step_width*10))+'%');
					tr.removeClass('active');
					spinner.setStyle('visibility','hidden');

					this.install(tasks, step_width);
				}
			}.bind(this),
			onError: function(text, error) {
				Joomla.renderMessages([['',Joomla.JText._('JLIB_DATABASE_ERROR_DATABASE', 'A Database error occurred.')]]);
				Install.goToPage('summary');
			}.bind(this),
			onFailure: function(xhr) {
				var r = JSON.decode(xhr.responseText);
				if (r) {
					Joomla.replaceTokens(r.token);
					alert(r.message);
				}
			}.bind(this)
		}).send();
	},

	/**
 	 * Method to install sample data via AJAX request.
	 */
	sampleData: function(el, filename) {
		this.busy = true;
		sample_data_spinner = new Spinner('collapseSample');
		sample_data_spinner.show(true);
		el = document.id(el);
		filename = document.id(filename);
		var req = new Request.JSON({
			method: 'get',
			url: 'index.php?'+document.id(el.form).toQueryString(),
			data: {'task':'setup.loadSampleData', 'format':'json'},
			onRequest: function() {
				el.set('disabled', 'disabled');
				filename.set('disabled', 'disabled');
				document.id('theDefaultError').setStyle('display','none');
			},
			onSuccess: function(r) {
				if (r) {
					Joomla.replaceTokens(r.token);
					this.sampleDataLoaded = r.data.sampleDataLoaded;
					if (r.error == false) {
						el.set('text', Joomla.JText._('INSTL_SITE_SAMPLE_LOADED', 'Sample Data Installed Successfully.'));
						el.set('onclick','');
						el.set('disabled', 'disabled');
						filename.set('disabled', 'disabled');
					} else {
						document.id('theDefaultError').setStyle('display','block');
						document.id('theDefaultErrorMessage').set('html', r.message);
						el.set('disabled', '');
						filename.set('disabled', '');
					}
				} else {
					document.id('theDefaultError').setStyle('display','block');
					document.id('theDefaultErrorMessage').set('html', response );
					el.set('disabled', 'disabled');
					filename.set('disabled', 'disabled');
				}
				this.busy = false;
				sample_data_spinner.hide(true);
			}.bind(this),
			onFailure: function(xhr) {
				var r = JSON.decode(xhr.responseText);
				if (r) {
					Joomla.replaceTokens(r.token);
					document.id('theDefaultError').setStyle('display','block');
					document.id('theDefaultErrorMessage').set('html', r.message);
				}
				el.set('disabled', '');
				filename.set('disabled', '');
				this.busy = false;
				sample_data_spinner.hide(true);
			}
		}).send();
	},

	/**
 	 * Method to detect the FTP root via AJAX request.
 	 */
	detectFtpRoot: function(el) {
		el = document.id(el);
		var req = new Request.JSON({
			method: 'get',
			url: 'index.php?'+document.id(el.form).toQueryString(),
			data: {'task':'setup.detectFtpRoot', 'format':'json'},
			onRequest: function() {
				el.set('disabled', 'disabled');
			},
			onFailure: function(xhr) {
				var r = JSON.decode(xhr.responseText);
				if (r) {
					Joomla.replaceTokens(r.token)
					alert(xhr.status+': '+r.message);
				} else {
					alert(xhr.status+': '+xhr.statusText);
				}
			},
			onSuccess: function(r) {
				if (r) {
					Joomla.replaceTokens(r.token)
					if (r.error == false) {
						document.id('jform_ftp_root').set('value', r.data.root);
					} else {
						alert(r.message);
					}
				}
				el.set('disabled', '');
			}
		}).send();
	},

	verifyFtpSettings: function(el) {
		// make the ajax call
		el = document.id(el);
		var req = new Request.JSON({
			method: 'get',
			url: 'index.php?'+document.id(el.form).toQueryString(),
			data: {'task':'setup.verifyFtpSettings', 'format':'json'},
			onRequest: function() {
				el.set('disabled', 'disabled'); },
				onFailure: function(xhr) {
				var r = JSON.decode(xhr.responseText);
				if (r) {
					Joomla.replaceTokens(r.token)
					alert(xhr.status+': '+r.message);
				} else {
					alert(xhr.status+': '+xhr.statusText);
				}
			},
			onSuccess: function(r) {
				if (r) {
					Joomla.replaceTokens(r.token)
					if (r.error == false) {
						alert(Joomla.JText._('INSTL_FTP_SETTINGS_CORRECT', 'Settings correct'));
					} else {
						alert(r.message);
					}
				}
				el.set('disabled', '');
			},
			onError: function(response) {
				alert('error');
			}
		}).send();
	},

	/**
	 * Method to remove the installation Folder after a successful installation.
 	 */
	removeFolder: function(el) {
		el = document.id(el);
		var req = new Request.JSON({
			method: 'get',
			url: 'index.php?'+document.id(el.form).toQueryString(),
			data: {'task':'setup.removeFolder', 'format':'json'},
			onRequest: function() {
				el.set('disabled', 'disabled');
				document.id('theDefaultError').setStyle('display','none');
			},
			onComplete: function(r) {
				if (r) {
					Joomla.replaceTokens(r.token);
					if (r.error == false) {
						el.set('value', r.data.text);
						el.set('onclick','');
						el.set('disabled', 'disabled');
					} else {
						document.id('theDefaultError').setStyle('display','block');
						document.id('theDefaultErrorMessage').set('html', r.message);
						el.set('disabled', '');
					}
				} else {
					document.id('theDefaultError').setStyle('display','block');
					document.id('theDefaultErrorMessage').set('html', r);
					el.set('disabled', 'disabled');
				}
			},
			onFailure: function(xhr) {
				var r = JSON.decode(xhr.responseText);
				if (r) {
					Joomla.replaceTokens(r.token);
					document.id('theDefaultError').setStyle('display','block');
					document.id('theDefaultErrorMessage').set('html', r.message);
				}
				el.set('disabled', '');
			}
		}).send();
	},

	addToggler: function() {
		new Fx.Accordion($$('h4.moofx-toggler'), $$('div.moofx-slider'), {
			onActive: function(toggler, i) {
				toggler.addClass('moofx-toggler-down');
			},
			onBackground: function(toggler, i) {
				toggler.removeClass('moofx-toggler-down');
			},
			duration: 300,
			opacity: false,
			alwaysHide:true,
			show: 1
		});
    },

	toggle: function(id, el, value) {
		var val = document.getElement('input[name=jform['+el+']]:checked').value;
		if(val == value) {
			document.id(id).setStyle('display', '');
		} else {
			document.id(id).setStyle('display', 'none');
		}
    }
});
