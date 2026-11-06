import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { API_BASE } from './api.config';

export interface Program {
  id?: number;
  name: string;
  duration: number;
  time_slot: string;
  channel: string;
}

export interface AddProgramResponse {
  success: boolean;
  id?: number;
  error?: string;
}

@Injectable({
  providedIn: 'root'
})
export class ProgramService {
  private readonly apiUrl = `${API_BASE}/api.php`;

  private readonly programAddedSource = new Subject<void>();
  readonly programAdded$ = this.programAddedSource.asObservable();

  constructor(private readonly http: HttpClient) {}

  getPrograms(): Observable<Program[]> {
    return this.http.get<Program[]>(
      `${this.apiUrl}?t=${Date.now()}`
    );
  }

  addProgram(program: Program): Observable<AddProgramResponse> {
    return this.http.post<AddProgramResponse>(
      this.apiUrl,
      program
    );
  }

  notifyProgramAdded(): void {
    this.programAddedSource.next();
  }
}