import React, { createContext, useContext, useEffect, useState } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

const THEME_KEY = 'appTheme';

const THEMES = {
  blue: {
    name: 'blue',
    label: 'Blue',
    primary: '#378ADD',
    primaryLight: '#E6F1FB',
    green: '#1D9E75',
    greenLight: '#E1F5EE',
    purple: '#7F77DD',
    purpleLight: '#EEECFB',
    orange: '#D85A30',
    orangeLight: '#FAEEDA',
    danger: '#E24B4A',
    dangerLight: '#FCEBEB',
    warning: '#BA7517',
    warningLight: '#FAEEDA',
    success: '#1D9E75',
    successLight: '#E1F5EE',
    bg: '#F4F6F9',
    card: '#FFFFFF',
    border: '#EFEFEF',
    text: '#1A1A2E',
    textSub: '#6B7280',
    textMuted: '#B0B7C3',
    navBg: '#FFFFFF',
  },
  green: {
    name: 'green',
    label: 'Green',
    primary: '#1D9E75',
    primaryLight: '#E1F5EE',
    green: '#0F9D58',
    greenLight: '#DDEEE4',
    purple: '#7F77DD',
    purpleLight: '#EEECFB',
    orange: '#D85A30',
    orangeLight: '#FAEEDA',
    danger: '#E24B4A',
    dangerLight: '#FCEBEB',
    warning: '#BA7517',
    warningLight: '#FAEEDA',
    success: '#1D9E75',
    successLight: '#E1F5EE',
    bg: '#F3F8F4',
    card: '#FFFFFF',
    border: '#E6EFED',
    text: '#152B24',
    textSub: '#4B6361',
    textMuted: '#8A9F98',
    navBg: '#FFFFFF',
  },
  purple: {
    name: 'purple',
    label: 'Purple',
    primary: '#7F77DD',
    primaryLight: '#EEECFB',
    green: '#1D9E75',
    greenLight: '#E1F5EE',
    purple: '#6D5DD3',
    purpleLight: '#EFEAFB',
    orange: '#D85A30',
    orangeLight: '#FAEEDA',
    danger: '#E24B4A',
    dangerLight: '#FCEBEB',
    warning: '#BA7517',
    warningLight: '#FAEEDA',
    success: '#1D9E75',
    successLight: '#E1F5EE',
    bg: '#F5F3FE',
    card: '#FFFFFF',
    border: '#E8E3FB',
    text: '#1E1B3B',
    textSub: '#6B668B',
    textMuted: '#958FB2',
    navBg: '#FFFFFF',
  },
  orange: {
    name: 'orange',
    label: 'Orange',
    primary: '#D85A30',
    primaryLight: '#FAEEDA',
    green: '#1D9E75',
    greenLight: '#E1F5EE',
    purple: '#7F77DD',
    purpleLight: '#EEECFB',
    orange: '#CD4A1F',
    orangeLight: '#FFE8DC',
    danger: '#E24B4A',
    dangerLight: '#FCEBEB',
    warning: '#BA7517',
    warningLight: '#FAEEDA',
    success: '#1D9E75',
    successLight: '#E1F5EE',
    bg: '#FEF6EE',
    card: '#FFFFFF',
    border: '#F2D8C3',
    text: '#3C2E25',
    textSub: '#7B675D',
    textMuted: '#A08F86',
    navBg: '#FFFFFF',
  },
};

const ThemeContext = createContext({
  themeName: 'blue',
  theme: THEMES.blue,
  setThemeName: () => {},
  themes: THEMES,
});

export function ThemeProvider({ children }) {
  const [themeName, setThemeNameState] = useState('blue');

  useEffect(() => {
    AsyncStorage.getItem(THEME_KEY)
      .then(value => {
        if (value && THEMES[value]) {
          setThemeNameState(value);
        }
      })
      .catch(() => {});
  }, []);

  const setThemeName = async (name) => {
    if (!THEMES[name]) return;
    setThemeNameState(name);
    try {
      await AsyncStorage.setItem(THEME_KEY, name);
    } catch (error) {
      console.log('Theme save error:', error.message);
    }
  };

  const theme = THEMES[themeName] || THEMES.blue;

  return (
    <ThemeContext.Provider value={{ themeName, theme, setThemeName, themes: THEMES }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  return useContext(ThemeContext);
}
