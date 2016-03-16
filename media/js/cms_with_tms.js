// JavaScript Document
jQuery.noConflict();

function cms_with_tms_addRow(tableID) {
	
	var table = document.getElementById(tableID);

	var rowCount = table.rows.length;
	var row = table.insertRow(rowCount);

	var cell1 = row.insertCell(0);
	var element1 = document.createElement("input");
	element1.type = "text";
	element1.name = "lang_name[]";
	cell1.appendChild(element1);

	var cell2 = row.insertCell(1);
	var element2 = document.createElement("input");	
	element2.type = "text";
	element2.name = "lang_code[]";
	cell2.appendChild(element2);

	var cell3 = row.insertCell(2);
	var element3 = document.createElement("input");
	element3.type = "radio";
	element3.name = "default";
	cell3.appendChild(element3);
	
	var cell4 = row.insertCell(3);
	var element4 = document.createElement("input");
	element4.type = "checkbox";
	element4.name = "chk";
	cell4.appendChild(element4);

}

function cms_with_tms_deleteRow(tableID) {
	try {
	  var table = document.getElementById(tableID);
	  var rowCount = table.rows.length;

	  for(var i=0; i<rowCount; i++) {
		  var row = table.rows[i];
		  var chkbox = row.cells[3].childNodes[0];
		  if(null != chkbox && true == chkbox.checked) {
			  table.deleteRow(i);
			  rowCount--;
			  i--;
		  }
	  }
	} catch(e) {
		alert(e);
	}
}

function validate_manage_lang(fm) {
	var error=0;
	var i=0;
	var radio_checked=0;
	var arr_lang_names = fm.elements["lang_name[]"];
	var arr_lang_codes = fm.elements["lang_code[]"];
	var arr_default_lang = fm.elements["default"];	
	var table = document.getElementById('dataTable');
	var rowCount = table.rows.length;		

	if (rowCount > 2) {	
	  for (i=0;i<rowCount-1;i++) {	  
		  if (arr_lang_names[i].value == '') {
			  error = 'Language name is empty!';
			  arr_lang_names[i].focus();
			  break;
		  } else if (arr_lang_codes[i].value == '') {
			  error = 'Language code is empty!';
			  arr_lang_codes[i].focus();
			  break;
		  } else if (arr_default_lang[i].checked) {
			  radio_checked=1;
			  arr_default_lang[i].value = arr_lang_codes[i].value;
		  }		
	  }	
	} else {
	  if (arr_lang_names.value == '') {
		  error = 'Language name is empty!';
		  arr_lang_names.focus();
	  } else if (arr_lang_codes.value == '') {
		  error = 'Language code is empty!';
		  arr_lang_codes.focus();
	  } else if (arr_default_lang.checked) {
		  radio_checked=1;
		  arr_default_lang.value = arr_lang_codes.value;
	  }
	}

	
	if (error) {
		alert(error);
		return false;
	} else {
	  if (radio_checked) {
	    fm.submit();
	  } else {
	    alert('Select one language as the default language!');
	    return false;
	  }		
	}
}

