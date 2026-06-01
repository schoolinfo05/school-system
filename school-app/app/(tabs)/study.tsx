// @ts-nocheck
import { useState, useRef, useEffect } from 'react';
import {
  View, Text, ScrollView, TextInput, TouchableOpacity,
  StyleSheet, ActivityIndicator, KeyboardAvoidingView, Platform
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import api from '../../src/api';

const QUICK_PROMPTS = [
  { label: '📝 Quiz me',    message: 'Generate a short quiz for me based on my weakest subject' },
  { label: '📖 Explain',    message: 'Explain the most important topic I need to review right now' },
  { label: '💡 Study tip',  message: 'Give me a study tip for my upcoming exams' },
  { label: '🔢 Math help',  message: 'Help me with Mathematics 10 — give me a practice problem' },
];

export default function Study() {
  const router = useRouter();
  const [allowed, setAllowed] = useState(false);
  const [messages, setMessages] = useState([{
    role: 'assistant',
    text: "Hi! I'm your AI study companion 🎓 I know your grades and can help you study smarter. Ask me anything or try a quick prompt below!"
  }]);
  const [input, setInput]     = useState('');
  const [loading, setLoading] = useState(false);
  const scrollRef             = useRef(null);

  useEffect(() => {
    AsyncStorage.getItem('role').then(role => {
      if (role !== 'teacher') setAllowed(true);
    });
  }, []);

  if (!allowed) return (
    <View style={styles.center}>
      <Text style={styles.notAvail}>Study AI is for students only</Text>
    </View>
  );

  const send = async (text) => {
    const msg = text || input.trim();
    if (!msg || loading) return;
    setInput('');
    setMessages(prev => [...prev, { role: 'user', text: msg }]);
    setLoading(true);
    try {
      const res = await api.post('/ai/chat', { message: msg });
      setMessages(prev => [...prev, { role: 'assistant', text: res.data.reply }]);
    } catch (e) {
      setMessages(prev => [...prev, { role: 'assistant', text: 'Sorry, I had trouble connecting. Please try again!' }]);
    } finally {
      setLoading(false);
      setTimeout(() => scrollRef.current?.scrollToEnd({ animated: true }), 100);
    }
  };

  return (
    <KeyboardAvoidingView style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      keyboardVerticalOffset={90}>
      <View style={styles.header}>
        <Text style={styles.title}>AI Study Companion</Text>
        <Text style={styles.sub}>Powered by Groq AI · Personalised for you</Text>
      </View>
      <ScrollView ref={scrollRef} style={styles.messages}
        contentContainerStyle={{ padding:16, gap:12 }}
        onContentSizeChange={() => scrollRef.current?.scrollToEnd({ animated: true })}>
        {messages.map((m, i) => (
          <View key={i} style={[styles.bubble, m.role==='user' ? styles.bubbleUser : styles.bubbleAI]}>
            {m.role === 'assistant' && <Text style={styles.aiLabel}>🎓 Study AI</Text>}
            <Text style={[styles.bubbleText, m.role==='user' ? styles.bubbleTextUser : styles.bubbleTextAI]}>
              {m.text}
            </Text>
          </View>
        ))}
        {loading && (
          <View style={styles.bubbleAI}>
            <Text style={styles.aiLabel}>🎓 Study AI</Text>
            <View style={{ flexDirection:'row', alignItems:'center', gap:8 }}>
              <ActivityIndicator size="small" color="#7F77DD"/>
              <Text style={{ fontSize:13, color:'#999' }}>Thinking...</Text>
            </View>
          </View>
        )}
      </ScrollView>
      {messages.length <= 2 && (
        <ScrollView horizontal showsHorizontalScrollIndicator={false}
          style={styles.quickRow} contentContainerStyle={{ paddingHorizontal:16, gap:8 }}>
          {QUICK_PROMPTS.map((p, i) => (
            <TouchableOpacity key={i} style={styles.quickBtn} onPress={() => send(p.message)}>
              <Text style={styles.quickText}>{p.label}</Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      )}
      <View style={styles.inputRow}>
        <TextInput style={styles.input} placeholder="Ask me anything..."
          placeholderTextColor="#aaa" value={input} onChangeText={setInput}
          multiline maxLength={500}/>
        <TouchableOpacity style={[styles.sendBtn, (!input.trim()||loading) && styles.sendBtnDisabled]}
          onPress={() => send()} disabled={!input.trim()||loading}>
          <Text style={styles.sendText}>↑</Text>
        </TouchableOpacity>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container:       { flex:1, backgroundColor:'#f5f5f5' },
  center:          { flex:1, justifyContent:'center', alignItems:'center' },
  notAvail:        { fontSize:15, color:'#888' },
  header:          { backgroundColor:'#7F77DD', padding:20, paddingTop:56 },
  title:           { color:'#fff', fontSize:20, fontWeight:'600' },
  sub:             { color:'rgba(255,255,255,0.75)', fontSize:12, marginTop:3 },
  messages:        { flex:1 },
  bubble:          { borderRadius:16, padding:12, maxWidth:'85%', marginBottom:8 },
  bubbleUser:      { backgroundColor:'#7F77DD', alignSelf:'flex-end', borderBottomRightRadius:4 },
  bubbleAI:        { backgroundColor:'#fff', alignSelf:'flex-start', borderBottomLeftRadius:4, borderWidth:0.5, borderColor:'#eee' },
  aiLabel:         { fontSize:11, color:'#7F77DD', fontWeight:'600', marginBottom:4 },
  bubbleText:      { fontSize:14, lineHeight:21 },
  bubbleTextUser:  { color:'#fff' },
  bubbleTextAI:    { color:'#333' },
  quickRow:        { maxHeight:48, marginBottom:8 },
  quickBtn:        { backgroundColor:'#fff', borderRadius:20, paddingHorizontal:14, paddingVertical:8, borderWidth:0.5, borderColor:'#ddd' },
  quickText:       { fontSize:13, color:'#555' },
  inputRow:        { flexDirection:'row', padding:12, gap:8, backgroundColor:'#fff', borderTopWidth:0.5, borderColor:'#eee' },
  input:           { flex:1, backgroundColor:'#f5f5f5', borderRadius:20, paddingHorizontal:16, paddingVertical:10, fontSize:14, color:'#333', maxHeight:100 },
  sendBtn:         { width:42, height:42, borderRadius:21, backgroundColor:'#7F77DD', justifyContent:'center', alignItems:'center', alignSelf:'flex-end' },
  sendBtnDisabled: { opacity:0.4 },
  sendText:        { color:'#fff', fontSize:20, fontWeight:'600' },
});
