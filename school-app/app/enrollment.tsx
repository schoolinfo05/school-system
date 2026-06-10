// @ts-nocheck
// app/enrollment.tsx — St. Cecilia's College Registration Form

import { useState, useEffect, useCallback } from 'react';
import {
  View, Text, ScrollView, StyleSheet, TextInput,
  TouchableOpacity, ActivityIndicator, Alert,
  KeyboardAvoidingView, Platform,
} from 'react-native';
import { useRouter } from 'expo-router';
import api from '../src/api';

const C = {
  primary: '#1A3A6B',
  accent:  '#8B1A1A',
  bg:      '#F8F7F4',
  card:    '#FFFFFF',
  border:  '#D4C9B8',
  text:    '#1F1F1F',
  subtext: '#555555',
  muted:   '#888888',
  blue:    '#1A3A6B',
};

const CURRENT_SY    = '2024-2025';
const SEMESTERS     = ['1st', '2nd', 'Summer'];
const YEAR_LEVELS   = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
const SEXES         = ['Male', 'Female'];
const CIVIL_STATUSES = ['Single', 'Married', 'Widowed'];
const SHS_STRANDS   = ['STEM', 'ABM', 'HUMSS', 'TVL', 'GAS'];

const STUDENT_TYPE_OPTIONS = [
  { label: 'Shiftee', value: 'shiftee', payload: 'transferee' },
  { label: 'Transferee', value: 'transferee', payload: 'transferee' },
  { label: 'Returnee', value: 'returnee', payload: 'returnee' },
  { label: 'New Student', value: 'new_student', payload: 'new_student' },
  { label: 'Old Student', value: 'old_student', payload: 'old_student' },
];

const REQUIREMENTS = [
  'Form 138-A', 'Birth Certificate (NSO)', 'Honorable Dismissal',
  'Certificate of Transfer Credentials', '1x1 Colored Picture (4 pcs.)',
  'Medical Certificate', 'Certificate of Good Moral', 'Drug Test',
];

const Field = ({ label, required, children }) => (
  <View style={s.fieldWrap}>
    <Text style={s.inputLabel}>{label}{required ? ' *' : ''}</Text>
    {children}
  </View>
);

export default function EnrollmentScreen() {
  const router = useRouter();
  const [loading, setLoading]           = useState(false);
  const [loadingSubjects, setLoadingSubjects] = useState(false);
  const [loadingCourses, setLoadingCourses] = useState(false);
  const [subjects, setSubjects]         = useState([]);
  const [courses, setCourses]           = useState([]);
  const [courseSearch, setCourseSearch] = useState('');
  const [courseDropdownOpen, setCourseDropdownOpen] = useState(false);

  const [form, setForm] = useState({
    student_type: '',
    shiftee_from: '', shiftee_to: '',
    id_no: '',
    academic_status: '',
    year_level: '', course_program: '', course_id: null,
    strand: '',
    semester: '1st', school_year: CURRENT_SY,
    program_type: 'college',
    surname: '', first_name: '', middle_name: '',
    year_born: '', month_born: '', day_born: '',
    sex: '', religion: '', civil_status: '',
    place_of_birth: '', home_address: '', mobile_no: '',
    prev_school: '', prev_school_address: '',
    father_name: '', father_occupation: '',
    mother_name: '', mother_occupation: '',
    email: '', password: '', password_confirmation: '',
    subject_ids: [],
  });
  const [existingStudent, setExistingStudent] = useState(null);
  const [lookupLoading, setLookupLoading] = useState(false);
  const [lookupMessage, setLookupMessage] = useState('');

  const set = useCallback((key, val) => setForm(p => ({ ...p, [key]: val })), []);

  const courseCodeFromName = (name) => {
    if (!name) return '';
    const words = name.trim().split(/\s+/).filter(Boolean);
    if (!words.length) return '';
    const first = words[0];
    const rest = words.slice(1);
    if (/^[A-Z]{1,4}$/.test(first) && rest.length) {
      return first + rest.map(w => w[0]?.toUpperCase() || '').join('');
    }
    return words.map(w => w[0]?.toUpperCase() || '').join('').slice(0, 5);
  };

  const normalizeStudentTypeForPayload = (type) => {
    const option = STUDENT_TYPE_OPTIONS.find(o => o.value === type);
    return option ? option.payload : type;
  };

  const fillExistingStudent = (student) => {
    const birth = student.birthdate ? new Date(student.birthdate) : null;
    setForm(prev => ({
      ...prev,
      email: student.email || prev.email,
      surname: student.last_name || prev.surname,
      first_name: student.first_name || prev.first_name,
      middle_name: student.middle_name || prev.middle_name,
      year_born: birth ? String(birth.getFullYear()) : prev.year_born,
      month_born: birth ? String(birth.getMonth() + 1).padStart(2, '0') : prev.month_born,
      day_born: birth ? String(birth.getDate()).padStart(2, '0') : prev.day_born,
      sex: student.gender ? (student.gender === 'female' ? 'Female' : 'Male') : prev.sex,
      religion: student.religion || prev.religion,
      civil_status: student.civil_status || prev.civil_status,
      place_of_birth: student.place_of_birth || prev.place_of_birth,
      mobile_no: student.phone || prev.mobile_no,
      home_address: student.address || prev.home_address,
      father_name: student.father_name || prev.father_name,
      father_occupation: student.father_occupation || prev.father_occupation,
      mother_name: student.mother_name || prev.mother_name,
      mother_occupation: student.mother_occupation || prev.mother_occupation,
      prev_school: student.prev_school || prev.prev_school,
      prev_school_address: student.prev_school_address || prev.prev_school_address,
      student_type: student.student_type || prev.student_type,
      academic_status: student.academic_status || prev.academic_status,
      shiftee_from: student.shiftee_from || prev.shiftee_from,
      shiftee_to: student.shiftee_to || prev.shiftee_to,
      program_type: student.program_type || prev.program_type,
      grade_level: student.grade_level || prev.grade_level,
      strand: student.strand || prev.strand,
      course_id: student.course_id || prev.course_id,
      course_program: student.course || prev.course_program,
      year_level: student.year_level || prev.year_level,
      school_year: student.school_year || prev.school_year,
      semester: student.semester || prev.semester,
    }));
  };

  const handleLookupStudent = async () => {
    if (!form.id_no.trim()) {
      return Alert.alert('Required', 'Enter a student ID number to look up.');
    }
    setLookupLoading(true);
    setLookupMessage('');
    try {
      const res = await api.get('/enrollment/lookup', { params: { id_no: form.id_no.trim() } });
      setExistingStudent(res.data);
      fillExistingStudent(res.data);
      setLookupMessage('Existing student found. Personal details have been prefilled.');
    } catch {
      setExistingStudent(null);
      setLookupMessage('Student lookup failed. Please try again.');
    } finally {
      setLookupLoading(false);
    }
  };

  useEffect(() => {
    if (form.student_type !== 'old_student') {
      setExistingStudent(null);
      setLookupMessage('');
    }
  }, [form.student_type]);

  useEffect(() => {
    setExistingStudent(null);
    setLookupMessage('');
  }, [form.id_no]);

  // Reset course/strand when switching program type
  useEffect(() => {
    setForm(p => ({ ...p, course_id: null, course_program: '', strand: '', year_level: '' }));
    setSubjects([]);
  }, [form.program_type]);

  useEffect(() => {
    const loadSubjects = async () => {
      setLoadingSubjects(true);
      try {
        const params = { program_type: form.program_type, semester: form.semester.toLowerCase() };

        if (form.program_type === 'college') {
          if (!form.course_id) { setSubjects([]); return; }
          params.course = form.course_program;
          if (form.year_level) params.year_level = form.year_level.replace(/[^0-9]/g, '');
        } else {
          if (!form.strand) { setSubjects([]); return; }
          params.strand = form.strand;
          if (form.year_level) params.year_level = form.year_level.replace(/[^0-9]/g, '');
        }

        const res = await api.get('/subjects', { params });
        setSubjects(res.data || []);
      } catch {
        console.log('Failed to load subjects');
      } finally {
        setLoadingSubjects(false);
      }
    };

    const loadCourses = async () => {
      if (form.program_type !== 'college') return;
      setLoadingCourses(true);
      try {
        const res = await api.get('/courses', {
          params: { program_type: 'college', search: courseSearch.trim() || undefined },
        });
        setCourses(res.data || []);
      } catch {
        console.log('Failed to load courses');
      } finally {
        setLoadingCourses(false);
      }
    };

    loadSubjects();
    loadCourses();
  }, [form.program_type, form.course_id, form.course_program, form.strand, form.year_level, form.semester, courseSearch]);

  useEffect(() => {
    if (form.academic_status === 'Regular') {
      setForm(prev => ({ ...prev, subject_ids: subjects.map(subject => subject.id) }));
    }
  }, [form.academic_status, subjects]);

  useEffect(() => {
    if (form.academic_status === 'Irregular') {
      setForm(prev => ({ ...prev, subject_ids: [] }));
    }
  }, [form.academic_status]);

  const toggleSubject = useCallback((id) => {
    if (form.academic_status === 'Regular') return;
    setForm(prev => ({
      ...prev,
      subject_ids: prev.subject_ids.includes(id)
        ? prev.subject_ids.filter(sid => sid !== id)
        : [...prev.subject_ids, id],
    }));
  }, [form.academic_status]);

  const handleSubmit = async () => {
    if (!form.email.trim())                           return Alert.alert('Required', 'Email is required.');
    if (!existingStudent && form.password.length < 8) return Alert.alert('Required', 'Password must be at least 8 characters.');
    if (!existingStudent && form.password !== form.password_confirmation) return Alert.alert('Error', 'Passwords do not match.');
    if (!form.surname.trim() || !form.first_name.trim()) return Alert.alert('Required', 'Full name is required.');
    if (!form.student_type)                           return Alert.alert('Required', 'Please select a student type.');
    if (!form.academic_status)                        return Alert.alert('Required', 'Please select Regular or Irregular.');
    if (form.student_type === 'old_student' && !form.id_no.trim()) return Alert.alert('Required', 'Enter your student ID number for old-student enrollment.');
    if (form.program_type === 'college' && !form.course_id) return Alert.alert('Required', 'Please select a college course from the list.');
    if (form.program_type === 'shs' && !form.strand)  return Alert.alert('Required', 'Please select a strand.');
    if (form.academic_status === 'Irregular' && !form.subject_ids.length) return Alert.alert('Required', 'Please select at least one subject.');
    if (form.academic_status === 'Regular' && !subjects.length) return Alert.alert('Required', 'No regular subjects are available for this program and semester.');

    const payload = {
      email: form.email,
      ...(existingStudent ? {} : {
        password: form.password,
        password_confirmation: form.password_confirmation,
      }),
      first_name: form.first_name,
      last_name: form.surname,
      middle_name: form.middle_name,
      birthdate: `${form.year_born}-${form.month_born.padStart(2,'0')}-${form.day_born.padStart(2,'0')}`,
      gender: form.sex.toLowerCase(),
      contact_number: form.mobile_no,
      address: form.home_address,
      place_of_birth: form.place_of_birth,
      religion: form.religion,
      civil_status: form.civil_status,
      student_type: normalizeStudentTypeForPayload(form.student_type),
      academic_status: form.academic_status,
      program_type: form.program_type,
      course_id: form.program_type === 'college' ? form.course_id : null,
      course: form.program_type === 'college' ? form.course_program : null,
      strand: form.program_type === 'shs' ? form.strand : null,
      year_level: form.year_level ? form.year_level.replace(/[^0-9]/g, '') : null,
      grade_level: form.program_type === 'shs' ? form.year_level.replace(/[^0-9]/g, '') : null,
      prev_school: form.prev_school,
      prev_school_address: form.prev_school_address,
      father_name: form.father_name,
      father_occupation: form.father_occupation,
      mother_name: form.mother_name,
      mother_occupation: form.mother_occupation,
      subject_ids: form.subject_ids,
      school_year: form.school_year,
      semester: form.semester.toLowerCase(),
      id_no: form.id_no,
    };

    setLoading(true);
    try {
      await api.post('/enrollment', payload);
      Alert.alert(
        '🎉 Application Submitted!',
        `Your registration has been submitted.\nTrack using: ${form.email}`,
        [{ text: 'OK', onPress: () => router.replace('/enrollment-status') }]
      );
    } catch (e) {
      Alert.alert('Error', e.response?.data?.message || 'Submission failed. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const inp = (field, extra = {}) => (
    <TextInput
      style={[s.input, extra.multiline && { height: 80, textAlignVertical: 'top' }]}
      value={form[field]}
      onChangeText={v => set(field, v)}
      {...extra}
    />
  );

  const Pill = ({ field, opt }) => (
    <TouchableOpacity
      style={[s.pill, form[field] === opt && s.pillActive]}
      onPress={() => set(field, opt)}
    >
      <Text style={[s.pillText, form[field] === opt && s.pillTextActive]}>{opt}</Text>
    </TouchableOpacity>
  );

  const PillRow = ({ field, options, label }) => (
    <View style={s.fieldWrap}>
      {label && <Text style={s.inputLabel}>{label}</Text>}
      <View style={s.pillRow}>
        {options.map(opt => <Pill key={opt} field={field} opt={opt} />)}
      </View>
    </View>
  );

  const CheckItem = ({ label, value }) => (
    <TouchableOpacity style={s.checkRow} onPress={() => set('student_type', value)}>
      <View style={[s.checkBox, form.student_type === value && s.checkBoxActive]}>
        {form.student_type === value && <Text style={s.checkMark}>✓</Text>}
      </View>
      <Text style={s.checkLabel}>{label}</Text>
    </TouchableOpacity>
  );

  const totalUnits = subjects
    .filter(sub => form.subject_ids.includes(sub.id))
    .reduce((acc, sub) => acc + (sub.units_lab || 0) + (sub.units_lec || 0), 0);

  const isSHS = form.program_type === 'shs';

  return (
    <KeyboardAvoidingView
      style={s.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        contentContainerStyle={s.body}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >

        {/* ── School Header ── */}
        <View style={s.schoolHeader}>
          <Text style={s.schoolName}>{`St. Cecilia's College - Cebu, Inc.`}</Text>
          <Text style={s.schoolSub}>De La Salle Supervised School</Text>
          <Text style={s.deptName}>HIGHER EDUCATION DEPARTMENT</Text>
          <Text style={s.formTitle}>REGISTRATION FORM</Text>
          <Text style={s.ayLine}>A.Y. {CURRENT_SY}</Text>
        </View>

        {/* ── Requirements Checklist ── */}
        <View style={s.card}>
          <Text style={s.sectionTitle}>📋 Requirements Checklist</Text>
          <Text style={s.sectionSubtitle}>Bring originals and photocopies upon enrollment</Text>
          <View style={s.reqGrid}>
            {REQUIREMENTS.map(r => (
              <View key={r} style={s.reqItem}>
                <Text style={s.reqBullet}>[ ]</Text>
                <Text style={s.reqText}>{r}</Text>
              </View>
            ))}
          </View>
        </View>

        {/* ── Student Classification ── */}
        <View style={s.card}>
          <Text style={s.sectionTitle}>Student Classification</Text>
          {STUDENT_TYPE_OPTIONS.map(option => (
            <CheckItem key={option.value} label={option.label} value={option.value} />
          ))}
          {form.student_type === 'shiftee' && (
            <View style={s.shifteeRow}>
              <View style={{ flex: 1 }}>
                <Text style={s.inputLabel}>From</Text>
                {inp('shiftee_from', { placeholder: 'Previous program' })}
              </View>
              <View style={{ flex: 1 }}>
                <Text style={s.inputLabel}>To</Text>
                {inp('shiftee_to', { placeholder: 'New program' })}
              </View>
            </View>
          )}
          <Field label="I.D. No.">
            {inp('id_no', { placeholder: 'Student ID number' })}
          </Field>
          {form.student_type === 'old_student' && (
            <>
              <TouchableOpacity
                style={s.lookupBtn}
                onPress={handleLookupStudent}
                disabled={lookupLoading}
              >
                {lookupLoading
                  ? <ActivityIndicator color="#fff" />
                  : <Text style={s.lookupBtnText}>Lookup Existing Student</Text>
                }
              </TouchableOpacity>
              {lookupMessage ? <Text style={s.lookupMessage}>{lookupMessage}</Text> : null}
              {existingStudent ? (
                <View style={s.lookupResult}>
                  <Text style={s.lookupResultText}>
                    Matched student: {existingStudent.student_id} · {existingStudent.first_name} {existingStudent.last_name}
                  </Text>
                </View>
              ) : null}
            </>
          )}
        </View>

        {/* ── Academic Information ── */}
        <View style={s.card}>
          <Text style={s.sectionTitle}>Academic Information</Text>

          <PillRow label="Academic Status *" field="academic_status" options={['Regular', 'Irregular']} />
          <PillRow label="Program Type *"    field="program_type"    options={['college', 'shs']} />

          {/* Grade Level (SHS) or Year Level (College) */}
          <Field label={isSHS ? 'Grade Level *' : 'Year Level *'}>
            <View style={s.pillRow}>
              {(isSHS ? ['Grade 11', 'Grade 12'] : YEAR_LEVELS).map(opt => (
                <Pill key={opt} field="year_level" opt={opt} />
              ))}
            </View>
          </Field>

          {/* Strand (SHS) or Course dropdown (College) */}
          {isSHS ? (
            <PillRow label="Strand *" field="strand" options={SHS_STRANDS} />
          ) : (
            <Field label="Course / Program *">
              <TouchableOpacity
                style={[s.dropdown, form.course_id && s.dropdownActive]}
                onPress={() => setCourseDropdownOpen(open => !open)}
              >
                <View style={s.dropdownLabelGroup}>
                  <Text style={s.dropdownLabel}>
                    {form.course_id ? courseCodeFromName(form.course_program) : 'Select course'}
                  </Text>
                  <Text style={s.dropdownValue} numberOfLines={1}>
                    {form.course_program || 'Tap to choose from the list'}
                  </Text>
                </View>
                <Text style={s.dropdownArrow}>{courseDropdownOpen ? '▲' : '▼'}</Text>
              </TouchableOpacity>

              {courseDropdownOpen && (
                <View style={s.dropdownPanel}>
                  <TextInput
                    style={[s.input, s.courseSearchInput]}
                    value={courseSearch}
                    onChangeText={setCourseSearch}
                    placeholder="Search course list..."
                    placeholderTextColor={C.muted}
                    autoCorrect={false}
                  />
                  {loadingCourses ? (
                    <ActivityIndicator size="small" color={C.primary} />
                  ) : courses.length ? (
                    <ScrollView
                      style={s.dropdownScroll}
                      keyboardShouldPersistTaps="handled"
                      nestedScrollEnabled
                      showsVerticalScrollIndicator
                    >
                      {courses.map(course => (
                        <TouchableOpacity
                          key={course.id}
                          style={[s.courseOption, form.course_id === course.id && s.courseOptionActive]}
                          onPress={() => {
                            set('course_id', course.id);
                            set('course_program', course.name);
                            setCourseDropdownOpen(false);
                          }}
                        >
                          <View style={s.courseOptionLeft}>
                            <View style={s.courseCodeBadge}>
                              <Text style={s.courseCodeText}>{courseCodeFromName(course.name)}</Text>
                            </View>
                            <View style={s.courseOptionText}>
                              <Text style={s.courseOptionName}>{course.name}</Text>
                              <Text style={s.courseOptionMeta}>College</Text>
                            </View>
                          </View>
                        </TouchableOpacity>
                      ))}
                    </ScrollView>
                  ) : (
                    <Text style={s.helperText}>No managed courses match your search.</Text>
                  )}
                </View>
              )}
            </Field>
          )}

          <PillRow label="Semester" field="semester" options={SEMESTERS} />
          <Field label="School Year">
            {inp('school_year', { placeholder: 'e.g. 2024-2025' })}
          </Field>
        </View>

        {/* ── Personal Information ── */}
        <View style={s.card}>
          <Text style={s.sectionTitle}>Personal Information</Text>

          <View style={s.row3}>
            <View style={{ flex: 1.2 }}>
              <Text style={s.inputLabel}>Surname *</Text>
              {inp('surname')}
            </View>
            <View style={{ flex: 1.2 }}>
              <Text style={s.inputLabel}>First Name *</Text>
              {inp('first_name')}
            </View>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>Middle Name</Text>
              {inp('middle_name')}
            </View>
          </View>

          <Text style={[s.inputLabel, { marginTop: 12 }]}>Date of Birth</Text>
          <View style={s.row3}>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>Year</Text>
              {inp('year_born', { keyboardType: 'numeric', placeholder: 'YYYY', maxLength: 4 })}
            </View>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>Month</Text>
              {inp('month_born', { keyboardType: 'numeric', placeholder: 'MM', maxLength: 2 })}
            </View>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>Day</Text>
              {inp('day_born', { keyboardType: 'numeric', placeholder: 'DD', maxLength: 2 })}
            </View>
          </View>

          <View style={s.row3}>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>Sex</Text>
              <View style={s.pillRow}>
                {SEXES.map(opt => <Pill key={opt} field="sex" opt={opt} />)}
              </View>
            </View>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>Religion</Text>
              {inp('religion')}
            </View>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>Civil Status</Text>
              <View style={s.pillRow}>
                {CIVIL_STATUSES.map(opt => <Pill key={opt} field="civil_status" opt={opt} />)}
              </View>
            </View>
          </View>

          <Field label="Mobile Number">
            {inp('mobile_no', { keyboardType: 'phone-pad', placeholder: '+63' })}
          </Field>
          <Field label="Place of Birth">
            {inp('place_of_birth')}
          </Field>
          <Field label="Home Address">
            {inp('home_address', { multiline: true })}
          </Field>
        </View>

        {/* ── Previous School ── */}
        <View style={s.card}>
          <Text style={s.sectionTitle}>Previous School</Text>
          <View style={s.row2}>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>School Name</Text>
              {inp('prev_school')}
            </View>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>School Address</Text>
              {inp('prev_school_address')}
            </View>
          </View>
        </View>

        {/* ── Parents / Guardian ── */}
        <View style={s.card}>
          <Text style={s.sectionTitle}>Parents / Guardian</Text>
          <View style={s.row2}>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>{"Father's Name"}</Text>
              {inp('father_name')}
            </View>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>Occupation</Text>
              {inp('father_occupation')}
            </View>
          </View>
          <View style={[s.row2, { marginTop: 10 }]}>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>{"Mother's Name / Guardian"}</Text>
              {inp('mother_name')}
            </View>
            <View style={{ flex: 1 }}>
              <Text style={s.inputLabel}>Occupation</Text>
              {inp('mother_occupation')}
            </View>
          </View>
        </View>

        {/* ── Subjects Table ── */}
        <View style={s.card}>
          <Text style={s.sectionTitle}>📚 Subjects to Enroll</Text>
          <Text style={s.sectionSubtitle}>
            {form.academic_status === 'Regular'
              ? `Regular load for ${form.semester} Semester, A.Y. ${form.school_year}`
              : `Select subjects for ${form.semester} Semester, A.Y. ${form.school_year}`}
          </Text>

          <View style={s.tableHeader}>
            <Text style={[s.thCell, { flex: 1.4 }]}>Code</Text>
            <Text style={[s.thCell, { flex: 2.5 }]}>Description</Text>
            <Text style={[s.thCell, { flex: 0.7 }]}>Lab</Text>
            <Text style={[s.thCell, { flex: 0.7 }]}>Lec</Text>
            <Text style={[s.thCell, { flex: 1.2 }]}>Days</Text>
            <Text style={[s.thCell, { flex: 1.2 }]}>Time</Text>
            <Text style={[s.thCell, { flex: 1 }]}>Room</Text>
          </View>

          {loadingSubjects ? (
            <ActivityIndicator style={{ margin: 20 }} color={C.primary} />
          ) : subjects.length > 0 ? (
            <>
              {subjects.map(subject => {
                const active = form.subject_ids.includes(subject.id);
                return (
                  <TouchableOpacity
                    key={subject.id}
                    style={[s.tableRow, active && s.tableRowActive]}
                    onPress={() => toggleSubject(subject.id)}
                    disabled={form.academic_status === 'Regular'}
                  >
                    <View style={[s.checkBox, { marginRight: 6 }, active && s.checkBoxActive]}>
                      {active && <Text style={s.checkMark}>✓</Text>}
                    </View>
                    <Text style={[s.tdCell, { flex: 1.2 }]}>{subject.code}</Text>
                    <Text style={[s.tdCell, { flex: 2.5 }]}>{subject.name}</Text>
                    <Text style={[s.tdCell, { flex: 0.7 }]}>{subject.units_lab ?? '-'}</Text>
                    <Text style={[s.tdCell, { flex: 0.7 }]}>{subject.units_lec ?? '-'}</Text>
                    <Text style={[s.tdCell, { flex: 1.2 }]}>{subject.days ?? '-'}</Text>
                    <Text style={[s.tdCell, { flex: 1.2 }]}>{subject.time ?? '-'}</Text>
                    <Text style={[s.tdCell, { flex: 1 }]}>{subject.room ?? '-'}</Text>
                  </TouchableOpacity>
                );
              })}
              <View style={s.totalRow}>
                <Text style={s.totalLabel}>Total Number of Units</Text>
                <Text style={s.totalValue}>{totalUnits}</Text>
              </View>
            </>
          ) : (
            <Text style={s.emptyText}>
              {isSHS && !form.strand
                ? 'Select a strand first to view available subjects.'
                : !isSHS && !form.course_id
                ? 'Select a course first to view available subjects.'
                : 'No subjects available for this program.'}
            </Text>
          )}
        </View>

        {/* ── Account Credentials ── */}
        <View style={s.card}>
          <Text style={s.sectionTitle}>Account Credentials</Text>
          <Text style={s.sectionSubtitle}>Used for login after approval</Text>

          <Field label="Email Address *">
            <TextInput
              style={s.input}
              value={form.email}
              onChangeText={v => set('email', v)}
              keyboardType="email-address"
              autoCapitalize="none"
              editable={!existingStudent}
            />
          </Field>
          {existingStudent ? (
            <Text style={s.helperText}>
              Existing student account found. Login email will be linked to the current student record.
            </Text>
          ) : (
            <>
              <Field label="Password *">
                {inp('password', { secureTextEntry: true })}
              </Field>
              <Field label="Confirm Password *">
                {inp('password_confirmation', { secureTextEntry: true })}
              </Field>
            </>
          )}
        </View>

        {/* ── Submit ── */}
        <TouchableOpacity style={s.submitBtn} onPress={handleSubmit} disabled={loading}>
          {loading
            ? <ActivityIndicator color="#fff" />
            : <Text style={s.submitBtnText}>Submit Application</Text>
          }
        </TouchableOpacity>

        <TouchableOpacity style={s.statusLink} onPress={() => router.push('/enrollment-status')}>
          <Text style={s.statusLinkText}>{'Already applied? Check your status \u2192'}</Text>
        </TouchableOpacity>

      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: C.bg },
  body: { padding: 16, paddingBottom: 100 },

  schoolHeader: {
    alignItems: 'center', backgroundColor: C.card,
    padding: 20, borderRadius: 12, marginBottom: 16,
    borderBottomWidth: 3, borderBottomColor: C.primary,
  },
  schoolName: { fontSize: 17, fontWeight: '800', color: C.primary, textAlign: 'center' },
  schoolSub:  { fontSize: 12, color: C.subtext, marginTop: 2 },
  deptName:   { fontSize: 14, fontWeight: '700', color: C.primary, marginTop: 6, textTransform: 'uppercase' },
  formTitle:  { fontSize: 20, fontWeight: '800', color: C.accent, marginTop: 4 },
  ayLine:     { fontSize: 13, color: C.muted, marginTop: 4 },

  card: {
    backgroundColor: C.card, borderRadius: 16,
    padding: 16, marginBottom: 14,
    borderWidth: 1, borderColor: C.border,
  },
  sectionTitle:    { fontSize: 16, fontWeight: '700', color: C.primary, marginBottom: 4 },
  sectionSubtitle: { fontSize: 13, color: C.subtext, marginBottom: 12 },

  reqGrid:   { flexDirection: 'row', flexWrap: 'wrap', gap: 4, marginTop: 8 },
  reqItem:   { flexDirection: 'row', alignItems: 'center', width: '50%', paddingVertical: 3 },
  reqBullet: { fontSize: 12, color: C.muted, marginRight: 6, fontFamily: 'monospace' },
  reqText:   { fontSize: 12, color: C.subtext, flex: 1 },

  checkRow:      { flexDirection: 'row', alignItems: 'center', paddingVertical: 8 },
  checkBox:      { width: 22, height: 22, borderWidth: 2, borderColor: C.border, borderRadius: 5, justifyContent: 'center', alignItems: 'center', marginRight: 10 },
  checkBoxActive:{ backgroundColor: C.primary, borderColor: C.primary },
  checkMark:     { color: '#fff', fontSize: 13, fontWeight: 'bold' },
  checkLabel:    { fontSize: 15, color: C.text },
  shifteeRow:    { flexDirection: 'row', gap: 10, marginLeft: 32, marginBottom: 8 },

  fieldWrap:  { marginTop: 10 },
  inputLabel: { fontSize: 13, color: C.subtext, fontWeight: '600', marginBottom: 5 },
  input: {
    borderWidth: 1, borderColor: C.border, borderRadius: 10,
    paddingHorizontal: 12, paddingVertical: 11,
    fontSize: 15, color: C.text, backgroundColor: '#FBFAF7',
  },

  row3: { flexDirection: 'row', gap: 8, marginTop: 10 },
  row2: { flexDirection: 'row', gap: 8 },

  pillRow:       { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 4 },
  pill:          { paddingHorizontal: 14, paddingVertical: 9, borderWidth: 1, borderColor: C.border, borderRadius: 20, backgroundColor: '#FBFAF7' },
  pillActive:    { borderColor: C.primary, backgroundColor: '#EEF4FF' },
  pillText:      { fontSize: 14, color: C.subtext, fontWeight: '500' },
  pillTextActive:{ color: C.primary, fontWeight: '700' },
  chipRow:       { flexDirection: 'row', flexWrap: 'wrap', marginTop: 8 },
  chip:          { borderWidth: 1, borderColor: C.border, borderRadius: 20, paddingHorizontal: 12, paddingVertical: 10, marginRight: 8, marginBottom: 8, backgroundColor: '#FBFAF7' },
  chipActive:    { borderColor: C.primary, backgroundColor: '#EEF4FF' },
  chipText:      { fontSize: 14, color: C.subtext },
  chipTextActive:{ color: C.primary, fontWeight: '700' },
  dropdown:      { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderWidth: 1, borderColor: C.border, borderRadius: 12, backgroundColor: '#FBFAF7', padding: 12, marginTop: 4 },
  dropdownActive:{ borderColor: C.primary },
  dropdownLabelGroup: { flex: 1, paddingRight: 8 },
  dropdownLabel:  { fontSize: 14, fontWeight: '700', color: C.primary },
  dropdownValue:  { fontSize: 13, color: C.subtext, marginTop: 4 },
  dropdownArrow:  { fontSize: 16, color: C.subtext },
  dropdownPanel:  { borderWidth: 1, borderColor: C.border, borderRadius: 12, backgroundColor: '#FFFFFF', marginTop: 8, overflow: 'hidden' },
  dropdownScroll: { maxHeight: 220 },
  courseOption:   { paddingVertical: 12, paddingHorizontal: 12, borderBottomWidth: 1, borderBottomColor: '#F0EDE6' },
  courseOptionActive: { backgroundColor: '#EEF4FF' },
  courseOptionLeft: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  courseCodeBadge: { width: 44, height: 44, borderRadius: 12, backgroundColor: '#E8F0FF', justifyContent: 'center', alignItems: 'center' },
  courseCodeText:  { fontSize: 13, fontWeight: '700', color: C.blue },
  courseOptionText: { flex: 1 },
  courseOptionName: { fontSize: 14, fontWeight: '700', color: C.text },
  courseOptionMeta: { fontSize: 12, color: C.subtext, marginTop: 2 },
  courseSearchInput: { marginTop: 4, marginBottom: 8 },
  helperText:    { color: C.muted, fontSize: 13, marginTop: 8 },

  tableHeader:   { flexDirection: 'row', backgroundColor: C.primary, borderRadius: 8, paddingVertical: 8, paddingHorizontal: 6, marginBottom: 4 },
  thCell:        { fontSize: 11, color: '#fff', fontWeight: '700', textAlign: 'center' },
  tableRow:      { flexDirection: 'row', alignItems: 'center', paddingVertical: 10, paddingHorizontal: 6, borderBottomWidth: 1, borderBottomColor: '#F0EBE0', backgroundColor: '#FBFAF7', borderRadius: 6, marginBottom: 3 },
  tableRowActive:{ backgroundColor: '#EEF4FF', borderColor: C.primary, borderWidth: 1 },
  tdCell:        { fontSize: 12, color: C.text, textAlign: 'center' },
  totalRow:      { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center', paddingTop: 10, paddingHorizontal: 6, borderTopWidth: 1.5, borderTopColor: C.primary, marginTop: 6 },
  totalLabel:    { fontSize: 13, fontWeight: '700', color: C.primary, marginRight: 12 },
  totalValue:    { fontSize: 16, fontWeight: '800', color: C.accent },
  emptyText:     { fontSize: 14, color: C.muted, textAlign: 'center', paddingVertical: 20 },

  lookupBtn:     { backgroundColor: C.accent, borderRadius: 10, paddingVertical: 12, paddingHorizontal: 16, alignItems: 'center', marginTop: 10, marginBottom: 8 },
  lookupBtnText: { color: '#fff', fontSize: 15, fontWeight: '700' },
  lookupMessage: { fontSize: 13, color: C.accent, marginBottom: 8, fontStyle: 'italic' },
  lookupResult:  { backgroundColor: '#FFF3E0', borderRadius: 10, padding: 12, marginTop: 8, borderLeftWidth: 4, borderLeftColor: C.accent },
  lookupResultText: { fontSize: 13, color: C.text, fontWeight: '600' },

  submitBtn:     { backgroundColor: C.primary, borderRadius: 12, padding: 18, alignItems: 'center', marginTop: 10 },
  submitBtnText: { color: '#fff', fontSize: 16, fontWeight: '700' },
  statusLink:    { alignItems: 'center', marginTop: 20 },
  statusLinkText:{ color: C.primary, fontSize: 14, fontWeight: '600' },
});
