import React, { useEffect, useState } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { ActivityIndicator, View, Text } from 'react-native';
import { setToken } from './src/api';

import LoginScreen     from './src/screens/LoginScreen';
import DashboardScreen from './src/screens/DashboardScreen';
import GradesScreen    from './src/screens/GradesScreen';
import ProfileScreen   from './src/screens/ProfileScreen';

const Stack = createNativeStackNavigator();
const Tab   = createBottomTabNavigator();

function TabIcon({ label, focused }) {
  const icons = { Today:'🏠', Grades:'📊', Profile:'👤' };
  return (
    <View style={{ alignItems:'center' }}>
      <Text style={{ fontSize:20 }}>{icons[label]}</Text>
      <Text style={{ fontSize:10, color: focused ? '#378ADD' : '#999', marginTop:2 }}>{label}</Text>
    </View>
  );
}

function MainTabs() {
  return (
    <Tab.Navigator screenOptions={({ route }) => ({
      headerShown: false,
      tabBarShowLabel: false,
      tabBarStyle: { height:70, paddingBottom:10, paddingTop:6 },
      tabBarActiveTintColor: '#378ADD',
      tabBarIcon: ({ focused }) => <TabIcon label={route.name} focused={focused}/>,
    })}>
      <Tab.Screen name="Today"   component={DashboardScreen}/>
      <Tab.Screen name="Grades"  component={GradesScreen}/>
      <Tab.Screen name="Profile" component={ProfileScreen}/>
    </Tab.Navigator>
  );
}

export default function App() {
  const [ready, setReady]       = useState(false);
  const [loggedIn, setLoggedIn] = useState(false);

  useEffect(() => {
    AsyncStorage.getItem('token').then(token => {
      if (token) { setToken(token); setLoggedIn(true); }
      setReady(true);
    });
  }, []);

  if (!ready) return (
    <View style={{ flex:1, justifyContent:'center', alignItems:'center' }}>
      <ActivityIndicator size="large" color="#378ADD"/>
    </View>
  );

  return (
    <NavigationContainer>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {loggedIn
          ? <Stack.Screen name="Main"  component={MainTabs}/>
          : <Stack.Screen name="Login" component={LoginScreen}/>
        }
      </Stack.Navigator>
    </NavigationContainer>
  );
}