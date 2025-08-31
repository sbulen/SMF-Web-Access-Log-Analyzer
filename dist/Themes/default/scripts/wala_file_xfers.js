// Web Access Log Analyzer (WALA) function to upload large files via fetch api.
async function walaUpload(file_type) {
	// Make sure file_type is valid & set some page elements based on that file type
	if ((file_type !== 'log') && (file_type !== 'country'))
		file_type = 'asn'
	let file_type_gz = 'file_' + file_type + '_gz';
	let file_type_wheel = 'file_' + file_type + '_wheel';
	let file_type_status = 'file_' + file_type + '_status';
	let prev_file = 'wala_file_' + file_type;
	let prev_dt = 'wala_update_' + file_type;

	// Sometimes the allowed chunk size is huge (wala_chunk_size, calc'd in php), and even
	// massive files get taken in one time-consuming bite...  It's preferred to use multiple
	// smaller bites if so, so screen can show timely updates when uploading large files.
	let file = document.getElementById(file_type_gz).files[0];
	let one_tenth = Math.round(file.size/10);
	wala_chunk_size = Math.min(one_tenth, wala_chunk_size);
	const CHUNK_SIZE = wala_chunk_size;

	let start = 0;
	let end = CHUNK_SIZE;
	let totalChunks = Math.ceil(file.size / CHUNK_SIZE);
	let index = 1;
	let uploadedChunks = 0;

	// Start the spinner...
	document.getElementById(file_type_wheel).style.visibility = 'visible';
	document.getElementById(file_type_status).textContent = Math.round(100*uploadedChunks/totalChunks) + wala_str_uploaded;
	document.getElementById(prev_file).textContent = '';
	document.getElementById(prev_dt).textContent = '';
	disable_controls();

	// Upload .gz file in chunks
	while (start < file.size) {
		const chunk = file.slice(start, end);

		const formData = new FormData();
		formData.append('chunk', chunk);
		formData.append('file_type', file_type);
		formData.append('name', file.name);
		formData.append('index', index);
		formData.append(smf_session_var, smf_session_id);

		// Note xml must be passed otherwise SMF will return a normal http template
		try {
			const response = await fetch(smf_scripturl + '?action=xmlhttp;sa=walachunk;xml', {
				method: 'POST',
				credentials: 'same-origin',
				body: formData, // Browser sets Content-Type: multipart/form-data
			});
			const result = await response.text();
			if (response.ok) {
				uploadedChunks++;
				document.getElementById(file_type_status).textContent = Math.round(100*uploadedChunks/totalChunks) + wala_str_uploaded;
			} else {
				// Note if errors are encountered up here, chunks won't match below...
				console.error(wala_str_error_chunk + ' ' + index);
				break;
			}
		} catch (error) {
			console.error(wala_str_error_chunk + ' ' + index + ': ', error);
			break;
		}

		start = end;
		end = start + CHUNK_SIZE;
		index++;
	}

	// Prep for import - split up .gz into .csvs & cache the lookups...
	document.getElementById(file_type_status).textContent = wala_str_prep;
	if (uploadedChunks === totalChunks) {
		const formData = new FormData();
		formData.append('file_type', file_type);
		formData.append('name', file.name);
		formData.append('total_chunks', totalChunks);
		formData.append(smf_session_var, smf_session_id);

		const chunkct_regex = /OK (\d{1,10}) chunks/;

		try {
			// Note xml must be passed in url here otherwise SMF will return a normal http template
			const response = await fetch(smf_scripturl + '?action=xmlhttp;sa=walaprep;xml', {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			});
			const result = await response.text();
			if (response.ok) {
				// Get the number of csv chunks
				totalChunks = result.match(chunkct_regex)[1];
			} else {
				console.error(wala_str_failed);
				new smc_Popup({
					heading: wala_str_loader,
					content: wala_str_failed,
					icon_class: "main_icons error",
				});
				// If errors, kill the spinner & exit...
				document.getElementById(file_type_wheel).style.visibility = 'hidden';
				return;
			}
		} catch (error) {
			console.error(wala_str_failed + ': ', error);
			new smc_Popup({
				heading: wala_str_loader,
				content: wala_str_failed,
				icon_class: "main_icons error",
			});
			document.getElementById(file_type_wheel).style.visibility = 'hidden';
			return;
		}
	} else {
		console.error(wala_str_failed);
		new smc_Popup({
			heading: wala_str_loader,
			content: wala_str_failed,
			icon_class: "main_icons error",
		});
		document.getElementById(file_type_wheel).style.visibility = 'hidden';
		return;
	}

	// Import csv chunks one at a time
	index = 0;
	document.getElementById(file_type_status).textContent = Math.round(100*index/totalChunks) + wala_str_imported;
	let error_found = false;
	index = 1;
	while (index <= totalChunks) {
		const formData = new FormData();
		formData.append('file_type', file_type);
		formData.append('name', file.name);
		formData.append('total_chunks', totalChunks);
		formData.append('index', index);
		formData.append(smf_session_var, smf_session_id);

		try {
			// Note xml must be passed otherwise SMF will return a normal http template
			const response = await fetch(smf_scripturl + '?action=xmlhttp;sa=walaimport;xml', {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			});
			const result = await response.text();
			if (response.ok) {
				document.getElementById(file_type_status).textContent = Math.round(100*index/totalChunks) + wala_str_imported;
			} else {
				console.error(wala_str_failed);
				new smc_Popup({
					heading: wala_str_loader,
					content: wala_str_failed,
					icon_class: "main_icons error",
				});
				error_found = true;
				break;
			}
		} catch (error) {
			console.error(wala_str_failed + ': ', error);
			new smc_Popup({
				heading: wala_str_loader,
				content: wala_str_failed,
				icon_class: "main_icons error",
			});
			error_found = true;
			break;
		}
		index++;
	}
	// Last but not least, hide the spinner...
	if (error_found === false) {
		new smc_Popup({
			heading: wala_str_loader,
			content: wala_str_success,
			icon_class: "main_icons reports",
		});
	}
	document.getElementById(file_type_wheel).style.visibility = 'hidden';
	// Give the success/failure message a few secs of air time, then reload
	setTimeout(() => {location.reload();}, 3000);
}

// Web Access Log Analyzer (WALA) function to load smf member data to reporting db.
async function walaMemberSync() {
	let file_type_wheel = 'file_member_wheel';
	let file_type_status = 'file_member_status';
	let prev_dt = 'wala_update_member';
	// Start with one chunk for now...  We won't know how many for real until server tells us later.
	let totalChunks = 1;
	let index = 0;
	let processedChunks = 0;
	const chunkct_regex = /OK (\d{1,10}) chunks/;

	// Start the spinner...
	document.getElementById(file_type_wheel).style.visibility = 'visible';
	document.getElementById(file_type_status).textContent = Math.round(100*processedChunks/totalChunks) + wala_str_imported;
	document.getElementById(prev_dt).textContent = '';
	disable_controls()

	// Load member reporting table one chunk at a time
	let error_found = false;
	while (processedChunks < totalChunks) {
		index++;
		const formData = new FormData();
		formData.append('index', index);
		formData.append(smf_session_var, smf_session_id);

		try {
			// Note xml must be passed otherwise SMF will return a normal http template
			const response = await fetch(smf_scripturl + '?action=xmlhttp;sa=walamemb;xml', {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			});
			const result = await response.text();
			if (response.ok) {
				processedChunks++;
				totalChunks = result.match(chunkct_regex)[1];
				document.getElementById(file_type_status).textContent = Math.round(100*processedChunks/totalChunks) + wala_str_imported;
			} else {
				console.error(wala_str_failed);
				new smc_Popup({
					heading: wala_str_loader,
					content: wala_str_failed,
					icon_class: "main_icons error",
				});
				error_found = true;
				break;
			}
		} catch (error) {
			console.error(wala_str_failed + ': ', error);
			new smc_Popup({
				heading: wala_str_loader,
				content: wala_str_failed,
				icon_class: "main_icons error",
			});
			error_found = true;
			break;
		}
	}
	// Last but not least, hide the spinner...
	if (error_found === false) {
		new smc_Popup({
			heading: wala_str_loader,
			content: wala_str_success,
			icon_class: "main_icons reports",
		});
	}
	document.getElementById(file_type_wheel).style.visibility = 'hidden';
	// Give the success/failure message a few secs of air time, then reload
	setTimeout(() => {location.reload();}, 3000);
}

// disable the controls during processing
function disable_controls() {
	document.getElementById('file_asn_gz').setAttribute('disabled', true);
	document.getElementById('file_country_gz').setAttribute('disabled', true);
	document.getElementById('file_log_gz').setAttribute('disabled', true);
	document.getElementById('file_log_upload').setAttribute('disabled', true);
	document.getElementById('file_member').setAttribute('disabled', true);
	document.getElementById('file_country_upload').setAttribute('disabled', true);
	document.getElementById('file_asn_upload').setAttribute('disabled', true);
}