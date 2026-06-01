// @ts-nocheck
import { useEffect, useState } from 'react';
import { View, Text, ScrollView, StyleSheet, ActivityIndicator, TouchableOpacity, TextInput, Alert } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import api from '../../src/api';

const QUARTERS = ['1','2','3','4'];

export default function TeacherGrades() {
  const { classId, subject } = useLocalSearchParams();
  const router = useRouter();
  const [data, setData]       = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving]   = useState(false);
  const [quarter, setQuarter] = useState('1');
  const [scores, setScores]   = useState({});

  useEffect(() => {
    if (!classId) { setLoading(false); return; }
    api.get(`/teacher/class/${classId}/students`).then(res => {
      setData(res.data);
      const initial = {};
      res.data.students.forEach(s => {
        const g = res.data.grades[s.id]?.find(g => g.quarter === '1');
        initial[s.id] = g?.score?.toString() ?? '';
      });
      setScores(initial);
    }).catch(e => console.log('Error:', e.message))
    .finally(() => setLoading(false));
  }, [classId]);

  useEffect(() => {
    if (!data) return;
    const updated = {};
    data.students.forEach(s => {
      const g = data.grades[s.id]?.find(g => g.quarter === quarter);
      updated[s.id] = g?.score?.toString() ?? '';
    });
    setScores(updated);
  }, [quarter, data]);

  const save = async () => {
    setSaving(true);
    try {
      const grades = data.students
        .filter(s => scores[s.id] && parseFloat(scores[s.id]) > 0)
        .map(s => ({
          student_id: s.id,
          quarter,
          score: parseFloat(scores[s.id]),
        }));

      if (grades.length === 0) {
        Alert.alert('No grades', 'Please enter at least one grade.');
        return;
      }

      await api.post(`/teacher/class/${classId}/grades`, { grades });
      Alert.alert('✓ Saved!', `Grades for Q${quarter} saved successfully!`);
    } catch (e) {
      Alert.alert('Error', 'Could not save grades. Please try again.');
      console.log(e.message);
    } finally {
      setSaving(false);
    }
  };

  if (!classId) return (
    <View style={styles.center}>
      <Text style={styles.noClass}>No class selected</Text>
      <TouchableOpacity onPress={() => router.back()}>
        <Text style={styles.backLink}>← Go to Classes</Text>
      </TouchableOpacity>
    </View>
  );

  if (loading) return (
    <View style={styles.center}>
      <ActivityIndicator size="large" color="#378ADD"/>
    </View>
  );

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()} style={styles.backBtn}>
          <Text style={styles.backBtnText}>← Classes</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Enter grades</Text>
        <Text style={styles.sub}>{subject}</Text>
      </View>

      <View style={styles.quarterRow}>
        {QUARTERS.map(q => (
          <TouchableOpacity key={q}
            style={[styles.qBtn, quarter === q && styles.qBtnActive]}
            onPress={() => setQuarter(q)}>
            <Text style={[styles.qText, quarter === q && styles.qTextActive]}>Q{q}</Text>
          </TouchableOpacity>
        ))}
      </View>

      <ScrollView style={{ flex:1 }} contentContainerStyle={{ padding:16 }}>
        {data?.students?.length === 0 && (
          <Text style={styles.empty}>No students found for this class.</Text>
        )}
        {data?.students?.map((s, i) => (
          <View key={i} style={styles.studentRow}>
            <View style={styles.avatar}>
              <Text style={styles.avatarText}>
                {s.first_name[0]}{s.last_name[0]}
              </Text>
            </View>
            <View style={{ flex:1 }}>
              <Text style={styles.studentName}>{s.first_name} {s.last_name}</Text>
              <Text style={styles.studentId}>{s.student_id}</Text>
            </View>
            <TextInput
              style={styles.scoreInput}
              value={scores[s.id] ?? ''}
              onChangeText={val => setScores(prev => ({ ...prev, [s.id]: val }))}
              keyboardType="numeric"
              placeholder="—"
              placeholderTextColor="#ccc"
              maxLength={5}/>
          </View>
        ))}
      </ScrollView>

      <View style={styles.footer}>
        <TouchableOpacity
          style={[styles.saveBtn, saving && styles.saveBtnDisabled]}
          onPress={save}
          disabled={saving}>
          {saving
            ? <ActivityIndicator color="#fff"/>
            : <Text style={styles.saveBtnText}>💾 Save grades for Q{quarter}</Text>
          }
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container:       { flex:1, backgroundColor:'#f5f5f5' },
  center:          { flex:1, justifyContent:'center', alignItems:'center', gap:12 },
  noClass:         { fontSize:15, color:'#888' },
  backLink:        { fontSize:14, color:'#378ADD' },
  header:          { backgroundColor:'#378ADD', padding:20, paddingTop:52 },
  backBtn:         { marginBottom:8 },
  backBtnText:     { color:'rgba(255,255,255,0.8)', fontSize:13 },
  title:           { color:'#fff', fontSize:20, fontWeight:'600' },
  sub:             { color:'rgba(255,255,255,0.8)', fontSize:13, marginTop:3 },
  quarterRow:      { flexDirection:'row', padding:12, gap:8, backgroundColor:'#fff', borderBottomWidth:0.5, borderColor:'#eee' },
  qBtn:            { flex:1, paddingVertical:8, borderRadius:10, alignItems:'center', backgroundColor:'#f5f5f5' },
  qBtnActive:      { backgroundColor:'#378ADD' },
  qText:           { fontSize:13, color:'#888', fontWeight:'500' },
  qTextActive:     { color:'#fff' },
  studentRow:      { flexDirection:'row', alignItems:'center', gap:12, backgroundColor:'#fff', borderRadius:12, padding:12, marginBottom:8 },
  avatar:          { width:40, height:40, borderRadius:20, backgroundColor:'#E6F1FB', justifyContent:'center', alignItems:'center' },
  avatarText:      { fontSize:14, fontWeight:'600', color:'#378ADD' },
  studentName:     { fontSize:14, fontWeight:'500', color:'#333' },
  studentId:       { fontSize:11, color:'#999', marginTop:1 },
  scoreInput:      { width:64, borderWidth:0.5, borderColor:'#ddd', borderRadius:10, padding:10, fontSize:16, textAlign:'center', color:'#333', backgroundColor:'#fafafa' },
  footer:          { padding:16, backgroundColor:'#fff', borderTopWidth:0.5, borderColor:'#eee' },
  saveBtn:         { backgroundColor:'#378ADD', borderRadius:12, padding:15, alignItems:'center' },
  saveBtnDisabled: { opacity:0.6 },
  saveBtnText:     { color:'#fff', fontWeight:'600', fontSize:15 },
  empty:           { textAlign:'center', color:'#999', fontSize:13, paddingVertical:20 },
});