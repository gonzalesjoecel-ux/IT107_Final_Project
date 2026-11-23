/*
 * signup.js — browser-side validation for the registration form.
 * The same rules run again on the server (includes/validation.php).
 */
(function () {
  'use strict';

  var form = document.getElementById('signup-form');
  if (!form) { return; }

  var MIN_USERNAME = 6;
  var MAX_USERNAME = 20;
  var MIN_PASSWORD = 8;
  var MAX_PASSWORD = 64;
  var NAME_MAX = 50;
  var PASSWORD_MIN_MESSAGE = 'Password must be at least ' + MIN_PASSWORD + ' characters.';

  var birthdate = form.querySelector('[name="birthdate"]');
  var ageField = form.querySelector('[name="age"]');
  var password = form.querySelector('[name="password"]');
  var confirm = form.querySelector('[name="confirm"]');
  var extension = form.querySelector('[name="name_extension"]');
  var extensionOther = form.querySelector('[name="name_extension_other"]');
  var extensionWrap = document.getElementById('su-ext-other-wrap');
  var accountType = form.querySelector('[name="account_type"]');
  var organization = form.querySelector('[name="organization_name"]');
  var organizationWrap = document.getElementById('su-org-wrap');
  var username = form.querySelector('[name="username"]');
  var privacy = form.querySelector('[name="privacy"]');
  var strengthBox = document.getElementById('su-strength');
  var strengthLabel = document.getElementById('su-strength-label');
  var questionSelects = [1, 2, 3].map(function (index) {
    return form.querySelector('[name="question_' + index + '"]');
  });

  var usernameStatus = { value: '', available: null };
  var usernameTimer = null;

  function trimmed(el) {
    return el ? String(el.value || '').trim() : '';
  }

  function setMsg(name, message, ok) {
    var box = form.querySelector('[data-msg-for="' + name + '"]');
    if (box) {
      box.textContent = message || '';
      box.classList.toggle('site-field__msg--ok', !!ok && !!message);
    }

    var el = form.querySelector('[name="' + name + '"]');
    if (!el) { return; }
    if (name === 'privacy') {
      el.classList.toggle('is-invalid', !!message);
    } else {
      el.classList.toggle('site-input--invalid', !!message);
    }
  }

  function questionValue(index) {
    var select = questionSelects[index - 1];
    return select ? String(select.value || '') : '';
  }

  function questionDuplicateError(index) {
    var value = questionValue(index);
    if (!value) { return ''; }

    for (var earlier = 1; earlier < index; earlier++) {
      if (questionValue(earlier) === value) {
        return 'Please choose a different question.';
      }
    }
    return '';
  }

  /*
   * Keep the three question selects mutually exclusive. The first question
   * that picked a value wins; a later duplicate is cleared so the form cannot
   * be submitted with the same question twice.
   */
  function syncQuestionOptions() {
    var values = questionSelects.map(function (select) {
      return select ? String(select.value || '') : '';
    });

    for (var i = 0; i < values.length; i++) {
      if (!values[i]) { continue; }

      for (var j = 0; j < i; j++) {
        if (values[j] !== values[i]) { continue; }

        values[i] = '';
        if (questionSelects[i]) {
          questionSelects[i].value = '';
          questionSelects[i].setAttribute('data-question-conflict', '1');
        }
        setMsg('question_' + (i + 1), 'Please choose a different question.');
        break;
      }
    }

    questionSelects.forEach(function (select, index) {
      if (!select) { return; }

      Array.prototype.forEach.call(select.options, function (option) {
        var value = option.value;
        option.disabled = !!value && values.some(function (used, usedIndex) {
          return usedIndex !== index && used === value;
        });
      });
    });
  }

  function isTitleWord(word) {
    return /^[A-Z][a-z]*$/.test(word);
  }

  function hasDoubleSpace(value) {
    return value.indexOf('  ') !== -1;
  }

  /* ---------- field rules (mirror of includes/validation.php) ---------- */

  function idError(value) {
    if (!value) { return 'ID Number is required.'; }
    if (!/^\d{4}-\d{4}$/.test(value)) { return 'ID Number must follow the format xxxx-xxxx.'; }
    return '';
  }

  function nameError(value, label, required) {
    if (!value) { return required ? label + ' is required.' : ''; }
    if (value.length < 2) { return label + ' must be at least 2 characters.'; }
    if (value.length > NAME_MAX) { return label + ' must not exceed ' + NAME_MAX + ' characters.'; }
    if (/\d/.test(value)) { return 'Numbers are not allowed in ' + label + '.'; }
    if (/[^A-Za-z ]/.test(value)) { return 'Special characters are not allowed in ' + label + '.'; }
    if (hasDoubleSpace(value)) { return 'Double space is not allowed.'; }
    if (value === value.toUpperCase() && /[A-Z]/.test(value)) { return 'Do not use all capital letters.'; }
    if (/([A-Za-z])\1{2}/i.test(value)) { return 'Three consecutive identical letters are not allowed.'; }

    var words = value.split(' ');
    for (var i = 0; i < words.length; i++) {
      if (!isTitleWord(words[i])) { return 'First letter of your name must be capital.'; }
    }
    return '';
  }

  function extensionError(selectValue, otherValue) {
    if (selectValue !== 'Other') { return ''; }
    if (!otherValue) { return 'Please type your name extension.'; }
    if (!/^(I|II|III|IV|IX|X)$/.test(otherValue)) {
      return 'Name extension must be Jr., Sr., or a Roman numeral (I, II, III, IV, IX, X).';
    }
    return '';
  }

  function streetError(value) {
    if (!value) { return 'Purok / Street is required.'; }
    if (value.length < 3) { return 'Purok / Street must be at least 3 characters.'; }
    if (value.length > 50) { return 'Purok / Street must not exceed 50 characters.'; }
    if (/[^A-Za-z0-9 ]/.test(value)) { return 'Purok / Street may contain letters, digits and spaces only.'; }
    if (hasDoubleSpace(value)) { return 'Double space is not allowed.'; }
    return '';
  }

  function placeError(value, label, allowParticles) {
    if (!value) { return label + ' is required.'; }
    if (value.length < 2) { return label + ' must be at least 2 characters.'; }
    if (value.length > 50) { return label + ' must not exceed 50 characters.'; }
    if (/[^A-Za-z ]/.test(value)) { return label + ' may contain letters and spaces only.'; }
    if (hasDoubleSpace(value)) { return 'Double space is not allowed.'; }
    if (value === value.toUpperCase() && /[A-Z]/.test(value)) { return 'Do not use all capital letters.'; }

    var particles = ['de', 'del', 'dela', 'la', 'las', 'los', 'ng', 'sa', 'ug', 'of'];
    var words = value.split(' ');
    for (var i = 0; i < words.length; i++) {
      if (allowParticles && particles.indexOf(words[i].toLowerCase()) !== -1) { continue; }
      if (!isTitleWord(words[i])) {
        return label + ' must be in Title Case (for example, Cagayan de Oro).';
      }
    }
    return '';
  }

  function zipError(value) {
    if (!value) { return 'ZIP Code is required.'; }
    if (!/^\d{4}$/.test(value)) { return 'ZIP Code must be exactly 4 digits.'; }
    return '';
  }

  function emailError(value) {
    if (!value) { return 'Email is required.'; }
    if (value.length > 100) { return 'Email must not exceed 100 characters.'; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) { return 'Please enter a valid email address.'; }
    return '';
  }

  function mobileError(value) {
    if (!value) { return 'Recovery mobile number is required.'; }
    if (!/^09\d{9}$/.test(value)) { return 'Recovery mobile number must be 11 digits and start with 09.'; }
    return '';
  }

  function usernameError(value) {
    if (!value) { return 'Username is required.'; }
    if (!/^[A-Za-z]/.test(value)) { return 'Username must start with a letter.'; }
    if (value.length < MIN_USERNAME) { return 'Username must be at least ' + MIN_USERNAME + ' characters.'; }
    if (value.length > MAX_USERNAME) { return 'Username must not exceed ' + MAX_USERNAME + ' characters.'; }
    if (!/^[A-Za-z0-9]+$/.test(value)) { return 'Username may contain letters and digits only.'; }
    return '';
  }

  function strengthKind(value) {
    var classes = 0;
    if (/[a-z]/.test(value)) { classes++; }
    if (/[A-Z]/.test(value)) { classes++; }
    if (/\d/.test(value)) { classes++; }
    if (/[^A-Za-z0-9]/.test(value)) { classes++; }

    if (value.length >= 12 && classes >= 3) { return 'strong'; }
    if (value.length >= MIN_PASSWORD && classes >= 2) { return 'medium'; }
    return 'weak';
  }

  function passwordError(value) {
    if (!value) { return 'Password is required.'; }
    if (value.length < MIN_PASSWORD) { return PASSWORD_MIN_MESSAGE; }
    if (value.length > MAX_PASSWORD) { return 'Password must not exceed ' + MAX_PASSWORD + ' characters.'; }
    if (strengthKind(value) === 'weak') {
      return 'Password is too weak. Use at least ' + MIN_PASSWORD
           + ' characters with a mix of uppercase and lowercase letters, numbers or symbols.';
    }
    return '';
  }

  function answerError(value) {
    if (!value) { return 'Answer is required.'; }
    if (value.length < 2 || value.length > 50) { return 'Answer must be between 2 and 50 characters.'; }
    if (/[^A-Za-z ]/.test(value)) { return 'Answer may contain letters and spaces only.'; }
    if (hasDoubleSpace(value)) { return 'Double space is not allowed.'; }
    return '';
  }

  function vaultError(value) {
    if (!value) { return 'Vault display name is required.'; }
    if (value.length < 3 || value.length > 30) { return 'Vault display name must be 3-30 characters.'; }
    if (/[^A-Za-z ]/.test(value)) { return 'Vault display name may contain letters and spaces only.'; }
    if (hasDoubleSpace(value)) { return 'Double space is not allowed.'; }
    var words = value.split(' ');
    for (var i = 0; i < words.length; i++) {
      if (!isTitleWord(words[i])) { return 'Vault display name must be in Title Case.'; }
    }
    return '';
  }

  function organizationError(value, required) {
    if (!value) { return required ? 'Organization name is required for organization accounts.' : ''; }
    if (value.length < 2 || value.length > 50) { return 'Organization name must be 2-50 characters.'; }
    if (/[^A-Za-z0-9. ]/.test(value)) { return 'Organization name may contain letters, digits, spaces and periods only.'; }
    if (hasDoubleSpace(value)) { return 'Double space is not allowed.'; }
    var words = value.split(' ');
    for (var i = 0; i < words.length; i++) {
      if (!/^[A-Z][A-Za-z0-9.]*$/.test(words[i])) { return 'Organization name must be in Title Case.'; }
    }
    return '';
  }

  function birthdateResult() {
    var value = trimmed(birthdate);
    if (!value) { return { age: null, error: 'Birthdate is required.' }; }

    var date = new Date(value + 'T00:00:00');
    if (isNaN(date.getTime())) { return { age: null, error: 'Please enter a valid birthdate.' }; }

    var now = new Date();
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    if (date > today) { return { age: null, error: 'Birthdate cannot be in the future.' }; }

    var age = today.getFullYear() - date.getFullYear();
    var month = today.getMonth() - date.getMonth();
    if (month < 0 || (month === 0 && today.getDate() < date.getDate())) { age--; }

    if (age < 18) { return { age: age, error: 'You must be at least 18 years old to register.' }; }
    return { age: age, error: '' };
  }

  function refreshAge() {
    var result = birthdateResult();
    ageField.value = result.age === null ? '' : String(result.age);
    setMsg('birthdate', result.error);
    return result.error;
  }

  function refreshStrength() {
    if (!strengthBox || !password) { return; }
    if (!password.value) {
      strengthBox.hidden = true;
      strengthBox.setAttribute('data-strength', 'weak');
      strengthLabel.textContent = 'Weak';
      return;
    }

    var kind = strengthKind(password.value);
    strengthBox.hidden = false;
    strengthBox.setAttribute('data-strength', kind);
    strengthLabel.textContent = kind.charAt(0).toUpperCase() + kind.slice(1);
  }

  function validateField(name) {
    var el = form.querySelector('[name="' + name + '"]');
    if (!el) { return ''; }
    var value = trimmed(el);

    switch (name) {
      case 'id_number': return idError(value);
      case 'first_name': return nameError(value, 'First Name', true);
      case 'middle_name': return nameError(value, 'Middle Name', false);
      case 'family_name': return nameError(value, 'Family Name', true);
      case 'name_extension': return extensionError(extension.value, trimmed(extensionOther));
      case 'house_street': return streetError(value);
      case 'barangay': return placeError(value, 'Barangay', true);
      case 'municipality_city': return placeError(value, 'Municipality / City', true);
      case 'province': return placeError(value, 'Province', true);
      case 'country': return placeError(value, 'Country', true);
      case 'postal_code': return zipError(value);
      case 'email': return emailError(value);
      case 'recovery_mobile': return mobileError(value);
      case 'vault_display_name': return vaultError(value);
      case 'account_type':
        return ['Personal', 'Student', 'Professional', 'Organization'].indexOf(value) === -1
          ? 'Please select a valid account type.'
          : '';
      case 'organization_name': return organizationError(value, accountType.value === 'Organization');
      case 'question_1':
      case 'question_2':
      case 'question_3': {
        var index = Number(name.slice(-1));
        var select = questionSelects[index - 1];
        if (select && select.getAttribute('data-question-conflict') === '1') {
          return 'Please choose a different question.';
        }
        if (!value) { return 'Please choose a security question.'; }
        return questionDuplicateError(index);
      }
      case 'answer_1':
      case 'answer_2':
      case 'answer_3': return answerError(value);
      case 'captcha': return value ? '' : 'Please enter the characters shown in the image.';
      default: return '';
    }
  }

  /* ---------- live behaviour ---------- */

  var liveFields = [
    'id_number', 'first_name', 'middle_name', 'family_name',
    'house_street', 'barangay', 'municipality_city', 'province', 'country', 'postal_code',
    'email', 'recovery_mobile', 'vault_display_name', 'organization_name',
    'answer_1', 'answer_2', 'answer_3', 'captcha'
  ];

  liveFields.forEach(function (name) {
    var el = form.querySelector('[name="' + name + '"]');
    if (!el) { return; }
    el.addEventListener('blur', function () { setMsg(name, validateField(name)); });
    el.addEventListener('input', function () { setMsg(name, ''); });
  });

  questionSelects.forEach(function (select, index) {
    if (!select) { return; }
    select.addEventListener('change', function () {
      select.removeAttribute('data-question-conflict');
      setMsg('question_' + (index + 1), '');
      syncQuestionOptions();
    });
  });

  /* Re-apply exclusions on load so old values after a failed submit survive. */
  syncQuestionOptions();

  if (birthdate) {
    birthdate.addEventListener('change', refreshAge);
    birthdate.addEventListener('input', function () { setMsg('birthdate', ''); });
  }

  if (extension) {
    extension.addEventListener('change', function () {
      var isOther = extension.value === 'Other';
      if (extensionWrap) { extensionWrap.hidden = !isOther; }
      if (!isOther) { extensionOther.value = ''; }
      setMsg('name_extension', '');
    });
  }

  if (extensionOther) {
    extensionOther.addEventListener('input', function () { setMsg('name_extension', ''); });
  }

  if (accountType) {
    accountType.addEventListener('change', function () {
      var isOrganization = accountType.value === 'Organization';
      if (organizationWrap) { organizationWrap.hidden = !isOrganization; }
      if (!isOrganization) {
        organization.value = '';
        setMsg('organization_name', '');
      }
      setMsg('account_type', '');
    });
  }

  if (password) {
    password.addEventListener('input', function () {
      refreshStrength();
      setMsg('password', '');
    });
  }

  if (confirm) {
    confirm.addEventListener('input', function () { setMsg('confirm', ''); });
  }

  if (privacy) {
    privacy.addEventListener('change', function () { setMsg('privacy', ''); });
  }

  /* ---------- username availability (section 4.9) ---------- */

  if (username) {
    username.addEventListener('input', function () {
      var value = trimmed(username);
      clearTimeout(usernameTimer);
      setMsg('username', '');
      usernameStatus = { value: value, available: null };

      if (!value || usernameError(value)) { return; }

      usernameTimer = setTimeout(function () {
        var target = new URL('username_check.php', form.action);
        target.searchParams.set('username', value);

        fetch(target.toString(), { headers: { Accept: 'application/json' } })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (trimmed(username) !== value) { return; }
            usernameStatus = { value: value, available: !!data.available };
            if (!data.available) {
              setMsg('username', data.message || 'This username is already taken.');
            } else {
              setMsg('username', 'Username is available.', true);
            }
          })
          .catch(function () { /* the server check still applies */ });
      }, 300);
    });
  }

  /* ---------- submit ---------- */

  form.addEventListener('submit', function (event) {
    var firstBad = null;

    function check(name, message) {
      setMsg(name, message);
      if (message && !firstBad) { firstBad = name; }
    }

    function checkField(name) {
      check(name, validateField(name));
    }

    checkField('id_number');
    checkField('first_name');
    checkField('middle_name');
    checkField('family_name');

    var extensionMessage = extensionError(extension.value, trimmed(extensionOther));
    check('name_extension', extensionMessage);

    check('birthdate', birthdateResult().error);

    checkField('house_street');
    checkField('barangay');
    checkField('municipality_city');
    checkField('province');
    checkField('country');
    checkField('postal_code');

    checkField('email');
    checkField('recovery_mobile');

    var usernameMessage = usernameError(trimmed(username));
    if (!usernameMessage && usernameStatus.value === trimmed(username) && usernameStatus.available === false) {
      usernameMessage = 'This username is already taken.';
    }
    check('username', usernameMessage);

    check('password', passwordError(password.value));

    var confirmMessage = '';
    if (!confirm.value) {
      confirmMessage = 'Please re-enter your password.';
    } else if (confirm.value !== password.value) {
      confirmMessage = 'Passwords do not match.';
    }
    check('confirm', confirmMessage);

    syncQuestionOptions();
    checkField('question_1');
    checkField('question_2');
    checkField('question_3');

    checkField('answer_1');
    checkField('answer_2');
    checkField('answer_3');

    checkField('account_type');
    checkField('vault_display_name');
    check('organization_name', organizationError(trimmed(organization), accountType.value === 'Organization'));

    checkField('captcha');

    check('privacy', privacy.checked ? '' : 'You must agree to the Privacy Notice to register.');

    if (firstBad) {
      event.preventDefault();
      var target = form.querySelector('[name="' + firstBad + '"]');
      if (target) { target.focus(); }
    }
  });
})();
