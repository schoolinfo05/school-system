// @ts-nocheck
import { useEffect, useMemo, useState, useCallback } from 'react';
import {
  View, Text, ScrollView, StyleSheet, TextInput,
  TouchableOpacity, ActivityIndicator, RefreshControl,
  Platform, StatusBar, Modal, KeyboardAvoidingView, Alert,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import HeaderGradient from '../components/ui/HeaderGradient';
import api from '../../src/api';
import { useTheme } from '../../src/theme-context';

const HEADER_TOP = Platform.OS === 'android'
  ? (StatusBar.currentHeight ?? 24) + 10 : 52;

const STATUS_CONFIG = {
  active:    { label: 'Active',    bg: '#E1F5EE', color: '#1D9E75' },
  inactive:  { label: 'Inactive',  bg: '#F8F0F2', color: '#D85A30' },
  graduated: { label: 'Graduated', bg: '#E8F1FF', color: '#378ADD' },
};

export default function RegistrarStudents() {
  const { theme } = useTheme();
  const [students, setStudents]     = useState([]);
  const [loading, setLoading]       = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [search, setSearch]         = useState('');
  const [selected, setSelected]     = useState(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState(null);
  const [password, setPassword] = useState('');
  const totals = useMemo(() => ({
    active: students.filter(student => student.status === 'active').length,
    inactive: students.filter(student => student.status === 'inactive').length,
    graduated: students.filter(student => student.status === 'graduated').length,
  }), [students]);

  const fetchStudents = useCallback(async () => {
    try {
      const res = await api.get('/registrar/students');
      setStudents(res.data || []);
    } catch (e) {
      console.log('Students fetch error:', e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => { fetchStudents(); }, [fetchStudents]);

  const filteredStudents = search.trim()
    ? students.filter(student => {
        const term = search.trim().toLowerCase();
        return [student.first_name, student.last_name, student.email, student.student_id, student.grade_level, student.section]
          .some(value => value?.toString().toLowerCase().includes(term));
      })
    : students;

  const openDetail = (student) => {
    setSelected(student);
    setPassword('');
    setForm({
      student_id: student.student_id || '',
      first_name: student.first_name || '',
      last_name: student.last_name || '',
      email: student.email || student.user?.email || '',
      phone: student.phone || '',
      birthdate: student.birthdate || '',
      gender: student.gender || 'male',
      address: student.address || '',
      grade_level: student.grade_level || '',
      section: student.section || '',
      school_year: student.school_year || '',
      status: student.status || 'active',
    });
  };

  const setField = (key, value) => setForm(current => ({ ...current, [key]: value }));

  const saveStudent = async () => {
    if (!selected || !form) return;
    if (!form.student_id.trim() || !form.first_name.trim() || !form.last_name.trim() || !form.email.trim()) {
      Alert.alert('Missing details', 'Student ID, first name, last name, and email are required.');
      return;
    }
    setSaving(true);
    try {
      await api.put(`/registrar/students/${selected.id}`, {
        ...form,
        student_id: form.student_id.trim(),
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        email: form.email.trim(),
      });
      setSelected(null);
      await fetchStudents();
    } catch (e) {
      Alert.alert('Could not save student', e.response?.data?.message || 'Please check the student details.');
    } finally {
      setSaving(false);
    }
  };

  const resetPassword = async () => {
    if (!selected) return;
    if (password.trim().length < 6) {
      Alert.alert('Password too short', 'Use at least 6 characters.');
      return;
    }
    setSaving(true);
    try {
      await api.post(`/registrar/students/${selected.id}/reset-password`, { password: password.trim() });
      setPassword('');
      Alert.alert('Password updated', 'The student can now sign in with the new password.');
    } catch (e) {
      Alert.alert('Could not reset password', e.response?.data?.message || 'Please try again.');
    } finally {
      setSaving(false);
    }
  };

  const updateSelectedStudent = (student) => {
    setSelected(student);
    setStudents(current => current.map(item => item.id === student.id ? student : item));
  };

  const changeSubjectStatus = async (subject, action) => {
    if (!selected || !subject?.id) return;

    const verb = action === 'drop' ? 'Drop' : 'Restore';
    Alert.alert(
      `${verb} subject`,
      action === 'drop'
        ? `Drop ${subject.code || subject.name} for this student?`
        : `Restore ${subject.code || subject.name} to this student's study load?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: verb,
          style: action === 'drop' ? 'destructive' : 'default',
          onPress: async () => {
            setSaving(true);
            try {
              const res = await api.post(
                `/registrar/students/${selected.id}/subjects/${subject.id}/${action}`,
                { section_id: subject.section_id ?? null }
              );
              updateSelectedStudent(res.data.student);
            } catch (e) {
              Alert.alert('Could not update subject', e.response?.data?.message || 'Please try again.');
            } finally {
              setSaving(false);
            }
          },
        },
      ]
    );
  };

  return (
    <View style={[s.container, { backgroundColor: theme.bg }]}> 
      <HeaderGradient
        title="Students"
        subtitle="Review approved learners and student profiles."
        initials="ST"
        stats={[
          { label: 'Active', value: totals.active, accent: '#1D9E75' },
          { label: 'Inactive', value: totals.inactive, accent: '#D85A30' },
          { label: 'Graduated', value: totals.graduated, accent: '#378ADD' },
        ]}
      />

      <View style={s.searchRow}>
        <View style={[s.searchCard, { backgroundColor: theme.card, borderColor: theme.border }]}> 
          <Feather name="search" size={18} color="#9CA3AF" style={s.searchIcon} />
          <TextInput
            style={[s.searchInput, { color: theme.text }]}
            placeholder="Search students by name, email, or ID"
            placeholderTextColor={theme.textMuted}
            value={search}
            onChangeText={setSearch}
            returnKeyType="search"
          />
        </View>
      </View>

      {loading ? (
        <View style={[s.center, { backgroundColor: theme.bg }]}> 
          <ActivityIndicator size="large" color={theme.primary} />
        </View>
      ) : (
        <ScrollView
          style={s.list}
          contentContainerStyle={{ paddingBottom: 24 }}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchStudents(); }} />
          }
        >
          {filteredStudents.length === 0 ? (
            <View style={s.emptyWrap}>
              <Text style={s.emptyIcon}>🧑‍🎓</Text>
              <Text style={s.emptyTitle}>No students found</Text>
              <Text style={s.emptySub}>Try adjusting your search or refresh the list.</Text>
            </View>
          ) : filteredStudents.map((student, index) => {
            const status = STATUS_CONFIG[student.status] || STATUS_CONFIG.active;
            return (
              <TouchableOpacity
                key={index}
                style={[s.studentCard, { backgroundColor: theme.card, borderColor: theme.border }]}
                onPress={() => openDetail(student)}
                activeOpacity={0.8}
              >
                <View style={s.cardRow}>
                  <View style={[s.avatar, { backgroundColor: theme.successLight }]}> 
                    <Text style={[s.avatarText, { color: theme.success }]}> 
                      {student.first_name?.[0]}{student.last_name?.[0]}
                    </Text>
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={[s.studentName, { color: theme.text }]}>{student.first_name} {student.last_name}</Text>
                    <Text style={[s.studentMeta, { color: theme.textSub }]}>{student.student_id} · {student.email}</Text>
                    <Text style={[s.studentProgram, { color: theme.textMuted }]}>{student.grade_level} · {student.section} · {student.school_year}</Text>
                  </View>
                  <View style={[s.statusBadge, { backgroundColor: status.bg }]}> 
                    <Text style={[s.statusText, { color: status.color }]}>{status.label}</Text>
                  </View>
                </View>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
      )}

      <Modal visible={!!selected} animationType="slide" transparent>
        <KeyboardAvoidingView style={s.modalOverlay} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
          <View style={s.modalCard}>
            <View style={s.modalHeader}>
              <View style={{ flex: 1 }}>
                <Text style={s.modalTitle}>Student account</Text>
                <Text style={s.modalSub}>{selected?.user?.email || selected?.email || 'Linked student login'}</Text>
              </View>
              <TouchableOpacity style={s.modalClose} onPress={() => setSelected(null)}>
                <Text style={s.modalCloseText}>✕</Text>
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={s.form}>
              <Field label="Student ID" value={form?.student_id} onChangeText={value => setField('student_id', value)} />
              <View style={s.twoCol}>
                <View style={{ flex: 1 }}>
                  <Field label="First name" value={form?.first_name} onChangeText={value => setField('first_name', value)} />
                </View>
                <View style={{ flex: 1 }}>
                  <Field label="Last name" value={form?.last_name} onChangeText={value => setField('last_name', value)} />
                </View>
              </View>
              <Field label="Email" value={form?.email} onChangeText={value => setField('email', value)} keyboardType="email-address" autoCapitalize="none" />
              <Field label="Phone" value={form?.phone} onChangeText={value => setField('phone', value)} keyboardType="phone-pad" />

              <Text style={s.label}>Gender</Text>
              <View style={s.segment}>
                {['male', 'female'].map(gender => (
                  <TouchableOpacity
                    key={gender}
                    style={[s.segmentItem, form?.gender === gender && s.segmentActive]}
                    onPress={() => setField('gender', gender)}
                  >
                    <Text style={[s.segmentText, form?.gender === gender && s.segmentTextActive]}>{gender}</Text>
                  </TouchableOpacity>
                ))}
              </View>

              <View style={s.twoCol}>
                <View style={{ flex: 1 }}>
                  <Field label="Grade / Year" value={form?.grade_level} onChangeText={value => setField('grade_level', value)} />
                </View>
                <View style={{ flex: 1 }}>
                  <Field label="Section" value={form?.section} onChangeText={value => setField('section', value)} />
                </View>
              </View>
              <Field label="School year" value={form?.school_year} onChangeText={value => setField('school_year', value)} />
              <Field label="Birthdate" value={form?.birthdate} onChangeText={value => setField('birthdate', value)} placeholder="YYYY-MM-DD" />
              <Field label="Address" value={form?.address} onChangeText={value => setField('address', value)} multiline />

              <Text style={s.label}>Status</Text>
              <View style={s.statusPicker}>
                {Object.keys(STATUS_CONFIG).map(statusKey => {
                  const st = STATUS_CONFIG[statusKey];
                  return (
                    <TouchableOpacity
                      key={statusKey}
                      style={[s.statusOption, form?.status === statusKey && { backgroundColor: st.bg, borderColor: st.color }]}
                      onPress={() => setField('status', statusKey)}
                    >
                      <Text style={[s.statusOptionText, form?.status === statusKey && { color: st.color }]}>{st.label}</Text>
                    </TouchableOpacity>
                  );
                })}
              </View>

              <View style={s.infoPanel}>
                <Text style={s.infoTitle}>Parent / Guardian</Text>
                <InfoRow label="Father" value={selected?.enrollment?.father_name} />
                <InfoRow label="Father occupation" value={selected?.enrollment?.father_occupation} />
                <InfoRow label="Mother" value={selected?.enrollment?.mother_name} />
                <InfoRow label="Mother occupation" value={selected?.enrollment?.mother_occupation} />
              </View>

              <View style={s.infoPanel}>
                <Text style={s.infoTitle}>Previous school</Text>
                <InfoRow label="School" value={selected?.enrollment?.prev_school} />
                <InfoRow label="Address" value={selected?.enrollment?.prev_school_address} />
                <InfoRow label="Student type" value={formatValue(selected?.enrollment?.student_type)} />
                <InfoRow label="Academic status" value={selected?.enrollment?.academic_status} />
              </View>

              <View style={s.infoPanel}>
                <Text style={s.infoTitle}>Subjects</Text>
                {selected?.subjects?.length ? selected.subjects.map(subject => (
                  <View key={`${subject.source}-${subject.section_id ?? 'direct'}-${subject.id}`} style={s.subjectRow}>
                    <View style={{ flex: 1 }}>
                      <Text style={s.subjectName}>{subject.code ? `${subject.code} · ` : ''}{subject.name}</Text>
                      <Text style={s.subjectMeta}>
                        {subject.teacher ? `${subject.teacher} · ` : ''}
                        {subject.day ? `${subject.day} ` : ''}
                        {subject.time_start || subject.time_end ? `${subject.time_start || ''}-${subject.time_end || ''} ` : ''}
                        {subject.room || ''}
                      </Text>
                      {subject.status === 'dropped' && (
                        <Text style={s.droppedMeta}>
                          Dropped{subject.drop_reason ? `: ${subject.drop_reason}` : ''}
                        </Text>
                      )}
                    </View>
                    <View style={s.subjectActions}>
                      <Text style={[s.subjectUnits, subject.status === 'dropped' && s.subjectDropped]}>
                        {Number(subject.units_lec || 0) + Number(subject.units_lab || 0)}u
                      </Text>
                      {subject.status === 'dropped' ? (
                        <TouchableOpacity style={s.restoreSubjectBtn} onPress={() => changeSubjectStatus(subject, 'restore')} disabled={saving}>
                          <Text style={s.restoreSubjectText}>Restore</Text>
                        </TouchableOpacity>
                      ) : (
                        <TouchableOpacity style={s.dropSubjectBtn} onPress={() => changeSubjectStatus(subject, 'drop')} disabled={saving}>
                          <Text style={s.dropSubjectText}>Drop</Text>
                        </TouchableOpacity>
                      )}
                    </View>
                  </View>
                )) : (
                  <Text style={s.emptyInline}>No subjects found for this student.</Text>
                )}
              </View>

              <TouchableOpacity style={[s.saveBtn, saving && { opacity: 0.7 }]} onPress={saveStudent} disabled={saving}>
                {saving ? <ActivityIndicator color="#fff" /> : <Text style={s.saveText}>Save student</Text>}
              </TouchableOpacity>

              <View style={s.passwordBox}>
                <Text style={s.passwordTitle}>Reset login password</Text>
                <TextInput
                  style={s.input}
                  value={password}
                  onChangeText={setPassword}
                  placeholder="New password"
                  placeholderTextColor="#9CA3AF"
                  secureTextEntry
                />
                <TouchableOpacity style={s.resetBtn} onPress={resetPassword} disabled={saving}>
                  <Text style={s.resetText}>Update password</Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

function Field({ label, ...props }) {
  return (
    <>
      <Text style={s.label}>{label}</Text>
      <TextInput
        style={[s.input, props.multiline && { minHeight: 78, textAlignVertical: 'top', paddingTop: 12 }]}
        placeholderTextColor="#9CA3AF"
        {...props}
      />
    </>
  );
}

function InfoRow({ label, value }) {
  return (
    <View style={s.infoRow}>
      <Text style={s.infoLabel}>{label}</Text>
      <Text style={s.infoValue}>{value || '-'}</Text>
    </View>
  );
}

function formatValue(value) {
  if (!value) return '-';
  return String(value).replace(/_/g, ' ');
}

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F5F7FA' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  searchRow: { paddingHorizontal: 16, marginBottom: 16 },
  searchCard: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#fff', borderRadius: 16, paddingHorizontal: 14, height: 52, shadowColor: '#000', shadowOpacity: 0.08, shadowRadius: 12, elevation: 4 },
  searchIcon: { marginRight: 10 },
  searchInput: { flex: 1, color: '#0F172A', fontSize: 14, height: 52 },
  header: { backgroundColor: '#334155', paddingTop: HEADER_TOP, paddingHorizontal: 16, paddingBottom: 14 },
  title: { color: '#fff', fontSize: 24, fontWeight: '800' },
  subtitle: { color: 'rgba(255,255,255,0.78)', fontSize: 13, marginTop: 4 },
  filters: { gap: 8, paddingTop: 12 },
  filterChip: { borderRadius: 999, paddingHorizontal: 13, paddingVertical: 8, backgroundColor: 'rgba(255,255,255,0.12)' },
  filterChipActive: { backgroundColor: '#fff' },
  filterText: { color: 'rgba(255,255,255,0.78)', fontSize: 12, fontWeight: '800' },
  filterTextActive: { color: '#334155' },
  list: { flex: 1, paddingHorizontal: 12, paddingTop: 12 },
  card: { backgroundColor: '#fff', borderRadius: 12, padding: 14, marginBottom: 12, borderWidth: 1, borderColor: '#E5E7EB' },
  cardTop: { flexDirection: 'row', alignItems: 'center', gap: 11 },
  name: { color: '#111827', fontSize: 15, fontWeight: '800' },
  meta: { color: '#64748B', fontSize: 12, marginTop: 2 },
  program: { color: '#94A3B8', fontSize: 11, marginTop: 3 },
  statusBadge: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 6, alignSelf: 'flex-start' },
  statusText: { fontSize: 11, fontWeight: '900' },
  empty: { alignItems: 'center', paddingVertical: 80, paddingHorizontal: 30 },
  emptyTitle: { color: '#111827', fontSize: 16, fontWeight: '800' },
  emptySub: { color: '#64748B', fontSize: 13, textAlign: 'center', marginTop: 6 },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.45)', justifyContent: 'flex-end' },
  modalCard: { backgroundColor: '#fff', borderTopLeftRadius: 18, borderTopRightRadius: 18, maxHeight: '90%' },
  modalHeader: { padding: 18, borderBottomWidth: 1, borderColor: '#E5E7EB', flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  modalTitle: { color: '#111827', fontSize: 18, fontWeight: '900' },
  modalSub: { color: '#64748B', fontSize: 12, marginTop: 4 },
  modalClose: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
  modalCloseText: { color: '#334155', fontSize: 13, fontWeight: '900' },
  form: { padding: 18, paddingBottom: 30 },
  label: { color: '#334155', fontSize: 12, fontWeight: '900', marginBottom: 7, marginTop: 12 },
  input: { borderWidth: 1, borderColor: '#CBD5E1', borderRadius: 11, paddingHorizontal: 13, minHeight: 46, color: '#111827', fontSize: 14, backgroundColor: '#fff' },
  twoCol: { flexDirection: 'row', gap: 10 },
  segment: { flexDirection: 'row', gap: 8 },
  segmentItem: { flex: 1, borderWidth: 1, borderColor: '#CBD5E1', borderRadius: 11, paddingVertical: 12, alignItems: 'center' },
  segmentActive: { backgroundColor: '#F1F5F9', borderColor: '#334155' },
  segmentText: { color: '#475569', fontSize: 12, fontWeight: '900', textTransform: 'capitalize' },
  segmentTextActive: { color: '#111827' },
  statusPicker: { flexDirection: 'row', gap: 8 },
  statusOption: { flex: 1, borderWidth: 1, borderColor: '#CBD5E1', borderRadius: 11, paddingVertical: 12, alignItems: 'center' },
  statusOptionText: { color: '#475569', fontSize: 11, fontWeight: '900' },
  saveBtn: { marginTop: 22, backgroundColor: '#334155', borderRadius: 12, padding: 15, alignItems: 'center' },
  saveText: { color: '#fff', fontWeight: '900', fontSize: 14 },
  passwordBox: { marginTop: 18, padding: 14, borderRadius: 12, backgroundColor: '#F8FAFC', borderWidth: 1, borderColor: '#E2E8F0' },
  passwordTitle: { color: '#111827', fontWeight: '900', fontSize: 14, marginBottom: 8 },
  resetBtn: { marginTop: 10, backgroundColor: '#E0F2FE', borderRadius: 10, padding: 12, alignItems: 'center' },
  resetText: { color: '#0369A1', fontWeight: '900', fontSize: 13 },
  deleteBtn: { marginTop: 14, backgroundColor: '#FEE2E2', borderRadius: 12, padding: 15, alignItems: 'center' },
  deleteText: { color: '#B91C1C', fontWeight: '900', fontSize: 14 },
  infoPanel: { marginTop: 16, padding: 14, borderRadius: 12, backgroundColor: '#F8FAFC', borderWidth: 1, borderColor: '#E2E8F0' },
  infoTitle: { color: '#111827', fontSize: 14, fontWeight: '900', marginBottom: 8 },
  infoRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 12, paddingVertical: 7, borderBottomWidth: 1, borderColor: '#E2E8F0' },
  infoLabel: { color: '#64748B', fontSize: 12, fontWeight: '700', flex: 1 },
  infoValue: { color: '#111827', fontSize: 12, fontWeight: '800', flex: 1.25, textAlign: 'right' },
  subjectRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 10, borderBottomWidth: 1, borderColor: '#E2E8F0' },
  subjectName: { color: '#111827', fontSize: 13, fontWeight: '900' },
  subjectMeta: { color: '#64748B', fontSize: 11, marginTop: 3 },
  droppedMeta: { color: '#B91C1C', fontSize: 11, fontWeight: '800', marginTop: 4 },
  subjectActions: { alignItems: 'flex-end', gap: 6 },
  subjectUnits: { color: '#334155', fontSize: 12, fontWeight: '900', backgroundColor: '#E2E8F0', borderRadius: 999, paddingHorizontal: 9, paddingVertical: 5 },
  subjectDropped: { color: '#B91C1C', backgroundColor: '#FEE2E2' },
  dropSubjectBtn: { backgroundColor: '#FEE2E2', borderRadius: 8, paddingHorizontal: 10, paddingVertical: 7 },
  dropSubjectText: { color: '#B91C1C', fontSize: 11, fontWeight: '900' },
  restoreSubjectBtn: { backgroundColor: '#DCFCE7', borderRadius: 8, paddingHorizontal: 10, paddingVertical: 7 },
  restoreSubjectText: { color: '#166534', fontSize: 11, fontWeight: '900' },
  emptyInline: { color: '#64748B', fontSize: 12, paddingVertical: 8 },
  // keep some legacy styles used above
  studentCard: { backgroundColor: '#fff', borderRadius: 14, padding: 14, marginBottom: 12, borderWidth: 1, borderColor: '#E5E7EB' },
  cardRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  avatar: { width: 48, height: 48, borderRadius: 24, backgroundColor: '#E1F5EE', justifyContent: 'center', alignItems: 'center' },
  avatarText: { fontSize: 16, fontWeight: '700', color: '#1D9E75' },
  studentName: { fontSize: 15, fontWeight: '700', color: '#111827' },
  studentMeta: { fontSize: 12, color: '#6B7280', marginTop: 2 },
  studentProgram: { fontSize: 12, color: '#9CA3AF', marginTop: 2 },
});
