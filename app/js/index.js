const CONST_API_URL = '../php/api.php';
const CONST_DATE_INPUT_ID = '#date';
const CONST_EMPLOYEES_FORM_ID = '#employees-form';
const CONST_EMPLOYEES_TABLE_ID = '#employees-table';
const CONST_ADD_EMPLOYEE_BTN_DATASET = 'data-add-employee';
const CONST_SAVE_BTN_ID = '#save-raport-btn';
const CONST_EMPLOYEES_TABLE_WRAP_ID = '#employees-table-wrap';
const CONST_NOT_FOUND_MESSAGE = 'Ничего не найдено';
const CONST_NOT_FOUND_ID = '#app-not-found';
const CONST_REMOVE_EMPLOYEE_BTN_ID = '#remove-employee';
const CONST_INFO_BLOCK_ID = '#app-info-block';
const CONST_LOADING_ID = '#app-loading';
const CONST_SUMMARY_DAY_DATASET = 'data-day-date';
const CONST_PASSWORD = '12345';
const MAX_FILE_SIZE_MB = 10;
const CONST_MONTHS = [
  'Январь',
  'Февраль',
  'Март',
  'Апрель',
  'Май',
  'Июнь',
  'Июль',
  'Август',
  'Сентябрь',
  'Октябрь',
  'Ноябрь',
  'Декабрь',
];
const CONST_WEEKDAYS = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

const daysInMonth = (year, month) => new Date(year, month + 1, 0).getDate();
const loadingBlock = document.querySelector(CONST_LOADING_ID);
const clickHandlers = [];

// ========== FANCYBOX ==========
Fancybox.bind('[data-fancybox]', {});
//
// ========== INIT DATE ==========
const dateInput = document.querySelector(CONST_DATE_INPUT_ID);
dateInput.value = new Date().getFullYear();

const btnApplyDate = document.querySelector('#apply-date');
btnApplyDate.addEventListener('click', () => {
  createAccordion(dateInput.value);
});

// ========== START APP ==========
document.addEventListener('DOMContentLoaded', () => {
  createAccordion(new Date().getFullYear());
  initClickHandler();
  initFormSubmit();
  openCurDay(new Date().toISOString().split('T')[0]);
});

// ========== INIT GET CURRENT REPORTS ==========
function initGetCurrentReportsHandler(event) {
  const dayDate = event.target.getAttribute(CONST_SUMMARY_DAY_DATASET);
  if (!dayDate) return;
  getReports(dayDate);
}
clickHandlers.push(initGetCurrentReportsHandler);

// ========== GET DATE ==========
function getDate(year, mIndex, d) {
  return `${year}-${String(mIndex + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

// ========== OPEN CUR DAY ==========
function openCurDay(date) {
  const curDay = document.querySelector(`[data-day-date="${date}"]`);
  const curMonthDetails = curDay.closest('details[data-month]');
  const curDayDetails = curDay.closest('details');

  if (curDay) {
    curMonthDetails.open = true;
    curDayDetails.open = true;
    curDayDetails.scrollIntoView({ block: 'center' });
    getReports(curDay.getAttribute(CONST_SUMMARY_DAY_DATASET));
  }
}

// ========== INIT CLICK HANDLER ==========
function initClickHandler() {
  document.addEventListener('click', (e) => {
    clickHandlers.forEach((fn) => fn(e));
  });
}

// ========== INIT FORM SUBMIT ==========
function initFormSubmit() {
  const btnSave = document.querySelector(CONST_SAVE_BTN_ID);
  const form = document.querySelector(CONST_EMPLOYEES_FORM_ID);

  btnSave.addEventListener('click', () => form.requestSubmit());
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    saveReports();
  });
}

// ========== ADD EMPLOYEE ==========
function addEmployee(event) {
  const btnAddEmployee = event.target.closest('[data-add-employee]');
  if (!btnAddEmployee) return;
  console.log(btnAddEmployee);

  const curDate = btnAddEmployee.getAttribute(CONST_ADD_EMPLOYEE_BTN_DATASET);
  const tableId = `#employees-table-${curDate}`;
  createRow(tableId, curDate);
}
clickHandlers.push(addEmployee);

// ========== CREATE DAY CONTENT ==========
function createDayContent(year, month, day) {
  const dayContentDiv = document.createElement('div');
  dayContentDiv.className = 'day-content';
  const date = getDate(year, month, day);
  dayContentDiv.dataset.date = date;

  const tableWrapper = document.createElement('div');
  tableWrapper.className = 'table-wrap';

  const btnAddEmployee = document.createElement('button');
  btnAddEmployee.className = 'app-btn btn btn-accent mt-3 ms-auto';
  btnAddEmployee.textContent = '+ Добавить сотрудника';
  btnAddEmployee.setAttribute(CONST_ADD_EMPLOYEE_BTN_DATASET, date);
  btnAddEmployee.setAttribute('type', 'button');

  const table = document.createElement('table');
  table.id = `employees-table-${date}`;
  table.innerHTML = `
    <thead>
      <tr>
        <th rowspan="2">Сотрудники</th>
        <th colspan="5">До обеда</th>
        <th colspan="5">После обеда</th>
      </tr>
      <tr>
        <th>План задач (фото)</th><th>Комментарий к плану</th><th>Факт работы (фото)</th><th>Комментарий к факту</th><th>Статус</th>
        <th>План задач (фото)</th><th>Комментарий к плану</th><th>Факт работы (фото)</th><th>Комментарий к факту</th><th>Статус</th>
      </tr>
    </thead>
    <tbody>
    </tbody>
  `;

  tableWrapper.appendChild(table);
  dayContentDiv.appendChild(tableWrapper);
  dayContentDiv.appendChild(btnAddEmployee);
  return dayContentDiv;
}

// ========== CREATE ACCORDIONS ==========
function createAccordion(year) {
  const container = document.getElementById('app-year-content');
  container.innerHTML = '';

  CONST_MONTHS.forEach((month, mIndex) => {
    const monthDetails = document.createElement('details');
    const monthSummary = document.createElement('summary');
    monthSummary.textContent = month;
    monthDetails.appendChild(monthSummary);
    monthDetails.setAttribute('data-month', month);

    for (let d = 1; d <= daysInMonth(year, mIndex); d++) {
      const dayDetails = document.createElement('details');
      const weekday = CONST_WEEKDAYS[new Date(year, mIndex, d).getDay()];
      const daySummary = document.createElement('summary');
      daySummary.textContent = `${d} (${weekday})`;
      daySummary.setAttribute('data-day-date', getDate(year, mIndex, d));
      dayDetails.appendChild(daySummary);
      dayDetails.appendChild(createDayContent(year, mIndex, d));
      monthDetails.appendChild(dayDetails);
    }

    container.appendChild(monthDetails);
  });
}

// ========== EMPLOYEES TABLE ==========
const tdName = (index, id, value = '') => `
<!-- NAME -->
<td>
  <div class="app-td app-td-name">
    <button class="btn app-btn-icon remove" id="remove-employee" type="button">
      <img src="img/trash.svg" alt="delete"/>
    </button>
    <input type="text" required name="employees[${index}][${id}]" class="form-control" placeholder="Имя сотрудника" value="${value}">
  </div>
</td>
`;

const tdPhoto = (index, id, value = '') => `
<!-- PHOTO -->
<td>
  <div class="app-td app-img-upload" data-img-upload>
    <input type="file" class="form-control" name="employees[${index}][${id}]" accept="image/*">

    <div class="app-img-preview mt-2" style="${!value ? 'display: none' : ''}">
      <div class="app-img-delete" data-img-delete="${value}" data-img-field="${id}"></div>
      <a class="app-img-fancybox" data-fancybox href="${value}">
        <img src="${value}" alt="">
      </a>
    </div>
  </div>
</td>
`;

const tdComment = (index, id, value = '') => `
<!-- COMMENT -->
<td>
  <div class="app-td">
    <textarea name="employees[${index}][${id}]" class="form-control" rows="2">${value}</textarea>
  </div>
</td>
`;

const tdStatus = (index, id, value) => `
<!-- STATUS -->
<td>
  <div class="app-td">
    <select class="app-select-status form-control ${getStatusClass(value)}" name="employees[${index}][${id}]">
      <option value="?">?</option>
      <option value="done" ${value === 'done' ? 'selected' : ''}>Выполнен</option>
      <option value="not_done" ${value === 'not_done' ? 'selected' : ''}>Не выполнен</option>
    </select>
  </div>
</td>
`;

const inputDate = (index, value) => `
<!-- DATE -->
<input type="hidden" name="employees[${index}][date]" value="${value}">
`;

const inputCurId = (index, value) => `
<!-- ID -->
<input type="hidden" name="employees[${index}][id]" value="${value}">
`;

let employeesId = 0;
// ========== CREATE ROW EMPLOYEES TABLE ==========
function createRow(tableId, curDate) {
  const table = document.querySelector(tableId).querySelector('tbody');
  if (!table) {
    console.error('Table not found with ID:', tableId);
    return;
  }

  const tr = document.createElement('tr');
  tr.innerHTML = `
    ${tdName(employeesId, 'employee_name')}
    <!-- PRE LUNCH -->
    ${tdPhoto(employeesId, 'pre_lunch_photo_plan')}
    ${tdComment(employeesId, 'pre_lunch_comment_plan')}
    ${tdPhoto(employeesId, 'pre_lunch_photo_fact')}
    ${tdComment(employeesId, 'pre_lunch_comment_fact')}
    ${tdStatus(employeesId, 'pre_lunch_status')}
    <!-- AFTER LUNCH -->
    ${tdPhoto(employeesId, 'after_lunch_photo_plan')}
    ${tdComment(employeesId, 'after_lunch_comment_plan')}
    ${tdPhoto(employeesId, 'after_lunch_photo_fact')}
    ${tdComment(employeesId, 'after_lunch_comment_fact')}
    ${tdStatus(employeesId, 'after_lunch_status')}
    ${inputDate(employeesId, curDate)}
  `;

  // remove row
  tr.querySelector('.remove').addEventListener('click', () => {
    const password = prompt('Введите пароль для удаления:');
    if (password === CONST_PASSWORD) {
      tr.remove();
    } else {
      showNotification('Неверный пароль', false);
    }
  });

  table.appendChild(tr);
  employeesId++;
}

// Создание строки с данными из БД
function createRowWithData(tableId, report) {
  if (!report || !tableId) {
    console.error('Report or tableId not provided');
    return;
  }

  const tbody = document.querySelector(tableId).querySelector('tbody');
  if (!tbody) return;

  const tr = document.createElement('tr');
  tr.setAttribute('data-report-id', report.id);

  tr.innerHTML = `
        ${tdName(report.id, 'employee_name', report.employee_name)}
        <!-- PRE LUNCH -->
        ${tdPhoto(report.id, 'pre_lunch_photo_plan', report.pre_lunch_photo_plan)}
        ${tdComment(report.id, 'pre_lunch_comment_plan', report.pre_lunch_comment_plan)}
        ${tdPhoto(report.id, 'pre_lunch_photo_fact', report.pre_lunch_photo_fact)}
        ${tdComment(report.id, 'pre_lunch_comment_fact', report.pre_lunch_comment_fact)}
        ${tdStatus(report.id, 'pre_lunch_status', report.pre_lunch_status)}
        <!-- AFTER LUNCH -->
        ${tdPhoto(report.id, 'after_lunch_photo_plan', report.after_lunch_photo_plan)}
        ${tdComment(report.id, 'after_lunch_comment_plan', report.after_lunch_comment_plan)}
        ${tdPhoto(report.id, 'after_lunch_photo_fact', report.after_lunch_photo_fact)}
        ${tdComment(report.id, 'after_lunch_comment_fact', report.after_lunch_comment_fact)}
        ${tdStatus(report.id, 'after_lunch_status', report.after_lunch_status)}
        ${inputDate(report.id, report.report_date)}
        ${inputCurId(report.id, report.id)}
      `;

  // Обработчик удаления
  const removeBtn = tr.querySelector(CONST_REMOVE_EMPLOYEE_BTN_ID);
  if (removeBtn) {
    removeBtn.addEventListener('click', () => {
      const password = prompt('Введите пароль для удаления:');

      if (password === CONST_PASSWORD) {
        deleteReport(report.id, tr);
      } else {
        showNotification('Неверный пароль', false);
      }
    });
  }

  tbody.appendChild(tr);
}

// ========== SAVE REPORTS ==========
async function saveReports() {
  const form = document.querySelector(CONST_EMPLOYEES_FORM_ID);
  const formData = new FormData(form);

  try {
    loadingBlock.classList.add('active');
    const response = await fetch(`${CONST_API_URL}?action=save`, {
      method: 'POST',
      body: formData,
    });

    const result = await response.json();
    loadingBlock.classList.remove('active');

    if (result.success) {
      showNotification('Отчеты успешно сохранены!');
    } else {
      showNotification('Ошибка при сохранении: ' + result.error, false);
    }
  } catch (error) { }
}

// ========== SHOW DATA ==========
function showFormData(form) {
  if (!form) {
    console.error('Form not found with ID:', CONST_EMPLOYEES_FORM_ID);
    return;
  }

  const formData = new FormData(form);
  const entries = [];

  formData.forEach((value, key) => {
    entries.push({ key, value });
  });
  console.table(entries);
}

// ========== STATUS SELECT COLOR ==========
function updateSelectColor(select) {
  select.classList.remove('bg-success', 'bg-danger', 'text-white');

  if (select.value === 'done') {
    select.classList.add('bg-success-subtle', 'text-success');
  } else if (select.value === 'not_done') {
    select.classList.add('bg-danger-subtle', 'text-danger');
  }
}

document.addEventListener('change', (e) => {
  if (e.target.classList.contains('app-select-status')) {
    updateSelectColor(e.target);
  }
});

function getStatusClass(value) {
  if (value === 'done') {
    return 'bg-success-subtle text-success';
  } else if (value === 'not_done') {
    return 'bg-danger-subtle text-danger';
  }
}

document.querySelectorAll('.app-select-status').forEach(updateSelectColor);

// ========== IMG PREVIEW ==========
function updateImgPreview() {
  document.addEventListener('change', (e) => {
    const wrapper = e.target.closest('[data-img-upload]');
    const input = e.target;
    if (!input || !wrapper) return;

    const file = input.files[0];
    const imgWrapper = wrapper.querySelector('.app-img-preview');
    const imgFancybox = imgWrapper.querySelector('a');
    const img = imgWrapper.querySelector('img');

    if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
      showNotification('Размер файла не должен превышать 1мб', false);
      input.value = '';
      return;
    }

    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        img.src = e.target.result;
        imgFancybox.href = e.target.result;
        imgWrapper.style.display = 'block';
      };
      reader.readAsDataURL(file);
    } else {
      img.src = '';
      imgFancybox.href = '';
      imgWrapper.style.display = 'none';
    }
  });
}
updateImgPreview();

// Очистка таблицы
function clearTable(tableId) {
  if (!tableId) {
    console.error('Table ID not provided: clearTable');
    return;
  }

  const tbody = document.querySelector(`${tableId} tbody`);
  if (tbody) {
    tbody.innerHTML = '';
  }
}

async function getReports(date) {
  if (!date) {
    console.error('Date not provided');
    return;
  }

  try {
    const response = await fetch(`${CONST_API_URL}?action=get&date=${date}`, {
      method: 'GET',
    });
    const tableId = `#employees-table-${date}`;
    const result = await response.json();

    if (result.success && result.data.length > 0) {
      clearTable(tableId);

      result.data.forEach((report) => {
        createRowWithData(tableId, report);
      });
    } else {
      clearTable(tableId);
      console.log('No reports found for this date');
    }
  } catch (error) {
    console.error('Error fetching reports:', error);
    showNotification('Ошибка при загрузке отчетов', false);
  }
}

// Удаление отчета
async function deleteReport(id, tr) {
  try {
    const formData = new FormData();
    formData.append('id', id);

    const response = await fetch(`${CONST_API_URL}?action=delete`, {
      method: 'POST',
      body: formData,
    });

    const result = await response.json();

    if (result.success) {
      tr.remove();
      showNotification('Отчет удален');
    } else {
      showNotification('Ошибка при удалении отчета', false);
    }
  } catch (error) {
    console.error('Error deleting report:', error);
    showNotification('Ошибка при удалении отчета', false);
  }
}

// ========== DELETE IMAGE ==========
async function initDeleteImg(event) {
  const isDeleteBtn = event.target.closest('[data-img-delete]');
  const imgPreview = event.target.closest('.app-img-preview');
  if (!isDeleteBtn) return;

  if (!confirm('Вы уверены, что хотите удалить изображение?')) return;

  try {
    const formData = new FormData();
    const imgpath = isDeleteBtn.getAttribute('data-img-delete');
    const fieldName = isDeleteBtn.getAttribute('data-img-field');

    formData.append('img', imgpath);
    formData.append('fieldName', fieldName);

    const response = await fetch(`${CONST_API_URL}?action=delete_img`, {
      method: 'POST',
      body: formData,
    });

    const result = await response.json();
    if (result.success) {
      imgPreview.style.display = 'none';
      showNotification('Изображение удалено');
    } else {
      showNotification('Ошибка при удалении изображения', false);
    }
  } catch (error) {
    console.error('Error deleting image:', error);
    showNotification('Ошибка при удалении изображения', false);
  }
}
clickHandlers.push(initDeleteImg);

// ========== SHOW NOTIFICATION ==========
function showNotification(message, success = true) {
  const notification = document.querySelector(CONST_INFO_BLOCK_ID);
  if (!notification) return;

  notification.textContent = message;
  notification.classList.add('active');
  if (!success) {
    notification.classList.add('error');
  }

  setTimeout(() => {
    notification.classList.remove('active');
    notification.classList.remove('error');
  }, 3000);
}
